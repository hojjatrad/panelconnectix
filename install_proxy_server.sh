#!/bin/bash
# Install SOCKS5 + HTTP Proxy on VPN server for Connectix Panel v4.0.21
# This sets up 3proxy to accept username/password from panel clients
# Run on your VPN server (not panel host), as root

echo "=== Connectix Proxy Installer v4.0.21 ==="
echo "Installing SOCKS5 (1080) + HTTP (8080) proxy..."

# Check root
if [ "$EUID" -ne 0 ]; then
  echo "Please run as root: sudo bash install_proxy_server.sh"
  exit 1
fi

# Install dependencies
apt-get update
apt-get install -y build-essential wget curl ufw

# Install 3proxy
cd /tmp
wget https://github.com/3proxy/3proxy/archive/0.9.4.tar.gz
tar -xzf 0.9.4.tar.gz
cd 3proxy-0.9.4
make -f Makefile.Linux
mkdir -p /usr/local/3proxy/bin
mkdir -p /usr/local/3proxy/logs
mkdir -p /etc/3proxy
cp bin/3proxy /usr/local/3proxy/bin/
cp -r cfg /usr/local/3proxy/ 2>/dev/null || true

# Create config with auth
# NOTE: For simplicity, we create a single user that matches panel clients
# For per-client auth, you need to integrate with panel DB or use PAM
# Here we use users from /etc/3proxy/.proxyauth

cat > /etc/3proxy/3proxy.cfg <<'EOF'
# 3proxy config for Connectix Panel
daemon
maxconn 1000
nscache 65536
timeouts 1 5 30 60 180 1800 15 60
setgid 65535
setuid 65535
stacksize 6000
flush
auth strong
users testuser:CL:123456
# Add your panel clients here: user:CL:password
# Example: myclient:CL:mypassword
# You can also add via: echo "username:CL:password" >> /etc/3proxy/.proxyauth and include

# Allow all
allow *

# SOCKS5 on 1080
socks -p1080

# HTTP on 8080
proxy -p8080

# Logs
log /usr/local/3proxy/logs/3proxy.log D
logformat "- +_L%t.%. %N.%p %E %U %C:%c %R:%r %O %I %h %T"
rotate 30
EOF

# Create systemd service
cat > /etc/systemd/system/3proxy.service <<'EOF'
[Unit]
Description=3proxy Proxy Server for Connectix
After=network.target

[Service]
Type=simple
ExecStart=/usr/local/3proxy/bin/3proxy /etc/3proxy/3proxy.cfg
ExecStop=/bin/kill -TERM $MAINPID
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable 3proxy
systemctl restart 3proxy

# Open firewall
ufw allow 1080/tcp
ufw allow 8080/tcp
ufw allow 443/tcp
# Also try firewalld
firewall-cmd --permanent --add-port=1080/tcp 2>/dev/null
firewall-cmd --permanent --add-port=8080/tcp 2>/dev/null
firewall-cmd --reload 2>/dev/null

# iptables fallback
iptables -I INPUT -p tcp --dport 1080 -j ACCEPT 2>/dev/null
iptables -I INPUT -p tcp --dport 8080 -j ACCEPT 2>/dev/null
iptables-save > /etc/iptables/rules.v4 2>/dev/null || true

echo ""
echo "✅ 3proxy installed!"
echo "SOCKS5: $(curl -s ifconfig.me):1080"
echo "HTTP: $(curl -s ifconfig.me):8080"
echo ""
echo "Test: curl --proxy socks5://testuser:123456@127.0.0.1:1080 https://api.ipify.org"
echo ""
echo "To add a client from panel:"
echo "echo 'USERNAME:CL:PASSWORD' >> /etc/3proxy/.proxyauth"
echo "Then edit /etc/3proxy/3proxy.cfg and add user, or use include"
echo ""
echo "For per-client dynamic auth, integrate with panel API or use this script to sync:"
echo "https://vpbotn.ir/sync_proxy_users.php (we will create)"
echo ""
echo "Checking status:"
systemctl status 3proxy --no-pager -l | head -20
echo ""
echo "Logs: tail -f /usr/local/3proxy/logs/3proxy.log"
