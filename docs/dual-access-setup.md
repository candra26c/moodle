# Moodle Dual Access Setup
**Internet + Offline LAN fallback via Pi-hole + BIND9**

## Goal
`ujian.smamsevensby.com` accessible from:
- Internet (students/teachers from home)
- LAN when internet is down (during offline exams)

Same domain for both — no separate fake domain needed.

---

## How It Works

```
Internet ON:  Client → Cloudflare DNS → public IP → MikroTik → Coolify (172.16.0.53) → Traefik → Moodle
Internet OFF: Client → Pi-hole (172.16.0.54) → BIND9 → 172.16.0.53 → Traefik → Moodle
```

HTTPS works in both cases — Traefik serves from its cached Let's Encrypt cert (valid 90 days, auto-renews every 60).

---

## Layer 1 — Moodle Environment Variables (Coolify)

Set in Coolify → Moodle app → Environment Variables:

| Variable | Value |
|---|---|
| `MOODLE_URL` | `https://ujian.smamsevensby.com` |
| `MOODLE_LOCAL_URL` | `https://ujian.smamsevensby.com` |
| `MOODLE_LOCAL_HOST` | `ujian.smamsevensby.com` |

`config.php` already has the dual-URL switching logic — no code changes needed.

---

## Layer 2 — Pi-hole + BIND9 LXC on Proxmox

**LXC specs:** Ubuntu 22.04 · 1 vCPU · 512MB RAM · 4GB disk
**Static IP:** `172.16.0.54/22` · Gateway: `172.16.0.1`

### Step 1 — Install BIND9

```bash
apt update && apt install bind9 bind9utils -y
```

### Step 2 — Configure BIND9

`/etc/bind/named.conf.options`:
```
options {
    directory "/var/cache/bind";
    listen-on { 127.0.0.1; };
    allow-query { localhost; };
    forwarders { 1.1.1.1; 8.8.8.8; };
    forward only;
    dnssec-validation no;
};
```

`/etc/bind/named.conf.local`:
```
zone "smamsevensby.com" {
    type master;
    file "/etc/bind/zones/smamsevensby.com.db";
};
```

```bash
mkdir -p /etc/bind/zones
```

`/etc/bind/zones/smamsevensby.com.db`:
```
$TTL 300
@   IN SOA ns1.smamsevensby.com. admin.smamsevensby.com. (
        2026032701 ; serial (update on every change: YYYYMMDDNN)
        3600       ; refresh
        1800       ; retry
        604800     ; expire
        300 )      ; minimum TTL

@       IN NS  ns1.smamsevensby.com.
ns1     IN A   172.16.0.54

; All subdomains → Coolify/Traefik LAN IP
ujian   IN A   172.16.0.53
skool   IN A   172.16.0.53
home    IN A   172.16.0.53
perpus  IN A   172.16.0.53
```

```bash
named-checkzone smamsevensby.com /etc/bind/zones/smamsevensby.com.db
systemctl restart bind9
systemctl enable bind9
```

### Step 3 — Install Pi-hole

```bash
curl -sSL https://install.pi-hole.net | bash
```

During setup:
- Interface: `eth0`
- Upstream DNS: `Custom` → `127.0.0.1#53` (BIND9 on localhost)
- Enable web admin interface

### Step 4 — Point Pi-hole upstream to BIND9

Pi-hole admin → **Settings → DNS**:
- Uncheck all upstream providers
- Custom upstream 1: `127.0.0.1#53`

This makes Pi-hole ask BIND9 for all DNS. BIND9 answers `smamsevensby.com` from the local zone and forwards everything else to 1.1.1.1.

### Step 5 — Verify

```bash
# Test from inside the LXC
dig ujian.smamsevensby.com @127.0.0.1
# Should return 172.16.0.53

dig google.com @127.0.0.1
# Should return real public IP (forwarded to 1.1.1.1)
```

---

## Layer 3 — MikroTik DNS Redirect

Redirect all client DNS queries to Pi-hole (overrides MikroTik's built-in DNS).

**IP → Firewall → NAT** — add 2 rules, Rule 1 must be above Rule 2:

| Priority | Chain | Protocol | Dst-port | Src-address | Action | To-address |
|---|---|---|---|---|---|---|
| 1 | dstnat | tcp+udp | 53 | `172.16.0.54` | accept | — |
| 2 | dstnat | tcp+udp | 53 | any | dst-nat | `172.16.0.54` |

Rule 1 exempts Pi-hole itself from being redirected (prevents loop).
Rule 2 redirects everyone else to Pi-hole.

MikroTik terminal shortcut:
```
/ip firewall nat add chain=dstnat protocol=udp dst-port=53 src-address=172.16.0.54 action=accept place-before=0 comment="Exempt Pi-hole from DNS redirect"
/ip firewall nat add chain=dstnat protocol=udp dst-port=53 action=dst-nat to-addresses=172.16.0.54 comment="Redirect all DNS to Pi-hole"
/ip firewall nat add chain=dstnat protocol=tcp dst-port=53 src-address=172.16.0.54 action=accept place-before=0
/ip firewall nat add chain=dstnat protocol=tcp dst-port=53 action=dst-nat to-addresses=172.16.0.54
```

---

## What Happens During Internet Outage

| Check | Status |
|---|---|
| Pi-hole running | ✓ LAN service, unaffected by internet |
| BIND9 answers `ujian.smamsevensby.com` | ✓ local zone, no internet needed |
| Traefik serves HTTPS | ✓ cached cert, no internet needed |
| Moodle app | ✓ PostgreSQL + Redis all on LAN |
| External DNS (google.com etc.) | ✗ unavailable (expected) |

---

## Adding New Subdomains Later

1. Add A record to `/etc/bind/zones/smamsevensby.com.db`
2. Increment the serial number (YYYYMMDDNN format)
3. `systemctl restart bind9`

---

## Status

- [ ] LXC created at 172.16.0.54
- [ ] BIND9 installed and zone configured
- [ ] Pi-hole installed and pointing to BIND9
- [ ] MikroTik NAT rules applied
- [ ] Moodle env vars confirmed in Coolify
- [ ] Test: access `ujian.smamsevensby.com` from LAN with internet disconnected
