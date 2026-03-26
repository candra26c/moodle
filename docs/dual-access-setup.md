# Moodle Dual Access Setup
**Internet + Offline LAN fallback via Pi-hole + BIND9**

## Goal

`moodle.yourdomain.com` accessible from:
- Internet (students/teachers from home or mobile)
- LAN when internet is down (during offline exams on-site)

Same domain for both — no separate fake domain needed.

---

## How It Works

```
Internet ON:  Client → Public DNS (e.g. Cloudflare) → public IP → router → Coolify (YOUR_SERVER_IP) → Traefik → Moodle
Internet OFF: Client → Pi-hole (YOUR_PIHOLE_IP) → BIND9 → YOUR_SERVER_IP → Traefik → Moodle
```

HTTPS works in both cases — Traefik serves from its cached Let's Encrypt cert (valid 90 days, auto-renews every 60 days while internet is available).

---

## Layer 1 — Moodle Environment Variables (Coolify)

Set in Coolify → Moodle app → Environment Variables:

| Variable | Value |
|---|---|
| `MOODLE_URL` | `https://moodle.yourdomain.com` |
| `MOODLE_LOCAL_URL` | `https://moodle.yourdomain.com` |
| `MOODLE_LOCAL_HOST` | `moodle.yourdomain.com` |

`config.php` already has the dual-URL switching logic — no code changes needed.

---

## Layer 2 — Pi-hole + BIND9 (DNS server on your LAN)

Run this on any always-on server on your LAN (LXC, VM, or bare metal).

**Recommended static IP:** assign a fixed LAN IP to this server (e.g. `YOUR_PIHOLE_IP`).
**All clients must use this IP as their DNS server** — configured via your router/DHCP server.

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
zone "yourdomain.com" {
    type master;
    file "/etc/bind/zones/yourdomain.com.db";
};
```

```bash
mkdir -p /etc/bind/zones
```

`/etc/bind/zones/yourdomain.com.db`:
```
$TTL 300
@   IN SOA ns1.yourdomain.com. admin.yourdomain.com. (
        2024010101 ; serial (update on every change: YYYYMMDDNN)
        3600       ; refresh
        1800       ; retry
        604800     ; expire
        300 )      ; minimum TTL

@       IN NS  ns1.yourdomain.com.
ns1     IN A   YOUR_PIHOLE_IP

; Subdomains → your Coolify/server LAN IP
moodle  IN A   YOUR_SERVER_IP
```

> Add one `A` record per subdomain you want accessible offline.
> Increment the serial number each time you edit the zone file.

```bash
named-checkzone yourdomain.com /etc/bind/zones/yourdomain.com.db
systemctl restart bind9
systemctl enable bind9
```

### Step 3 — Install Pi-hole

```bash
curl -sSL https://install.pi-hole.net | bash
```

During setup:
- Interface: `eth0` (or your LAN interface)
- Upstream DNS: `Custom` → `127.0.0.1#53` (BIND9 on localhost)
- Enable web admin interface

### Step 4 — Point Pi-hole upstream to BIND9

Pi-hole admin → **Settings → DNS**:
- Uncheck all upstream providers
- Custom upstream 1: `127.0.0.1#53`

Pi-hole asks BIND9 for all DNS. BIND9 answers your domain from the local zone and forwards everything else to 1.1.1.1.

### Step 5 — Verify

```bash
# Test from inside the Pi-hole server
dig moodle.yourdomain.com @127.0.0.1
# Should return YOUR_SERVER_IP

dig google.com @127.0.0.1
# Should return real public IP (forwarded to 1.1.1.1)
```

---

## Layer 3 — Router/DHCP DNS Redirect

Point all client DNS queries to Pi-hole.

**Option A — Via DHCP server (recommended):**
Set the DNS server in your router's DHCP configuration to `YOUR_PIHOLE_IP`.
All clients that get an IP via DHCP will automatically use Pi-hole.

**Option B — Via MikroTik NAT (forces all DNS through Pi-hole):**

Add 2 rules in **IP → Firewall → NAT** (Rule 1 must be above Rule 2):

| Priority | Chain | Protocol | Dst-port | Src-address | Action | To-address |
|---|---|---|---|---|---|---|
| 1 | dstnat | tcp+udp | 53 | `YOUR_PIHOLE_IP` | accept | — |
| 2 | dstnat | tcp+udp | 53 | any | dst-nat | `YOUR_PIHOLE_IP` |

Rule 1 exempts Pi-hole itself (prevents loop). Rule 2 redirects everyone else.

MikroTik terminal:
```
/ip firewall nat add chain=dstnat protocol=udp dst-port=53 src-address=YOUR_PIHOLE_IP action=accept place-before=0 comment="Exempt Pi-hole from DNS redirect"
/ip firewall nat add chain=dstnat protocol=udp dst-port=53 action=dst-nat to-addresses=YOUR_PIHOLE_IP comment="Redirect all DNS to Pi-hole"
/ip firewall nat add chain=dstnat protocol=tcp dst-port=53 src-address=YOUR_PIHOLE_IP action=accept place-before=0
/ip firewall nat add chain=dstnat protocol=tcp dst-port=53 action=dst-nat to-addresses=YOUR_PIHOLE_IP
```

---

## What Happens During Internet Outage

| Check | Status |
|---|---|
| Pi-hole running | ✓ LAN service, unaffected by internet loss |
| BIND9 answers `moodle.yourdomain.com` | ✓ local zone, no internet needed |
| Traefik serves HTTPS | ✓ cert is cached locally, no internet needed |
| Moodle app + database | ✓ PostgreSQL + Redis are all on LAN |
| External DNS (google.com etc.) | ✗ unavailable — expected |

---

## Adding More Subdomains Later

1. Add an `A` record to your zone file
2. Increment the serial number (YYYYMMDDNN)
3. `systemctl restart bind9`

---

## Setup Checklist

- [ ] Pi-hole + BIND9 server running with a static LAN IP
- [ ] BIND9 zone file created for your domain
- [ ] Pi-hole upstream DNS set to BIND9 (`127.0.0.1#53`)
- [ ] Router DHCP hands out Pi-hole IP as DNS server (or NAT rule applied)
- [ ] Moodle env vars confirmed in Coolify (`MOODLE_LOCAL_HOST`, `MOODLE_LOCAL_URL`)
- [ ] Test: access `moodle.yourdomain.com` from LAN with internet disconnected
