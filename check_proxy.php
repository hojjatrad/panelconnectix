<?php
/**
 * Check Proxy Ports - Diagnose why Telegram proxy doesn't connect
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';

echo "<h2 style='font-family:sans-serif;direction:rtl'>🔍 بررسی پروکسی - چرا وصل نمیشه؟ v4.0.21</h2><div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px'>";

try {
    $pdo = Database::getConnection();
    $nodes = $pdo->query("SELECT * FROM server_nodes WHERE is_active=1 LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>1. سرورهای فعال:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;background:#1e293b'><tr><th>ID</th><th>نام</th><th>هاست</th><th>SOCKS</th><th>HTTP</th><th>MTProto</th><th>وضعیت پورت</th></tr>";
    foreach ($nodes as $n) {
        $host = $n['host'] ?? $n['sub_domain'] ?? '';
        if (empty($host) && !empty($n['api_url'])) {
            $p = parse_url($n['api_url']);
            $host = $p['host'] ?? '';
        }
        $host = trim(str_replace(['http://','https://'], '', $host));
        $host = explode(':', $host)[0];
        $host = explode('/', $host)[0];
        
        $socksPort = $n['socks_port'] ?? 1080;
        $httpPort = $n['http_port'] ?? 8080;
        $mtprotoPort = $n['mtproto_port'] ?? 443;
        
        // Test if ports open (from panel host perspective)
        $socksOpen = false;
        $httpOpen = false;
        $mtprotoOpen = false;
        
        if (!empty($host)) {
            $socksOpen = @fsockopen($host, $socksPort, $errno, $errstr, 3) ? true : false;
            if ($socksOpen) @fclose($socksOpen); $socksOpen = is_bool($socksOpen) ? false : $socksOpen;
            // Actually fsockopen returns resource on success
            $fp = @fsockopen($host, $socksPort, $errno, $errstr, 2);
            $socksStatus = $fp ? "✅ باز" : "❌ بسته ($errstr)";
            if ($fp) fclose($fp);
            
            $fp2 = @fsockopen($host, $httpPort, $errno, $errstr, 2);
            $httpStatus = $fp2 ? "✅ باز" : "❌ بسته ($errstr)";
            if ($fp2) fclose($fp2);
            
            $fp3 = @fsockopen($host, $mtprotoPort, $errno, $errstr, 2);
            $mtprotoStatus = $fp3 ? "✅ باز" : "❌ بسته ($errstr)";
            if ($fp3) fclose($fp3);
        } else {
            $socksStatus = $httpStatus = $mtprotoStatus = "❌ هاست نامشخص";
        }
        
        echo "<tr><td>{$n['id']}</td><td>{$n['name']}</td><td style='font-family:monospace'>$host</td><td>$socksPort</td><td>$httpPort</td><td>$mtprotoPort</td><td style='font-size:11px'>SOCKS: $socksStatus<br>HTTP: $httpStatus<br>MTProto: $mtprotoStatus</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>2. چرا وصل نمیشه؟ دلایل رایج:</h3>";
    echo "<div style='background:#451a03;padding:12px;border-radius:8px;border:1px solid #78350f'>";
    echo "<p><b>❌ پورت بسته:</b> اگر بالا ❌ بسته میبینی، یعنی فایروال سرور VPN پورت 1080/8080 را بسته. باید روی سرور VPN (نه هاست پنل) این دستورات را بزنی:</p>";
    echo "<code style='background:#000;padding:8px;display:block;margin:8px 0;direction:ltr'>sudo ufw allow 1080/tcp<br>sudo ufw allow 8080/tcp<br>sudo ufw allow 443/tcp<br>sudo iptables -I INPUT -p tcp --dport 1080 -j ACCEPT<br>sudo iptables -I INPUT -p tcp --dport 8080 -j ACCEPT</code>";
    echo "<p><b>❌ Xray SOCKS inbound ندارد:</b> پنل شما VLESS/VMESS میسازه ولی SOCKS inbound روی سرور Xray نداره. باید در پنل 3x-ui سرور بری:</p>";
    echo "<code style='background:#000;padding:8px;display:block;margin:8px 0'>3x-ui Panel → Inbounds → Add Inbound → SOCKS → Port 1080 → Save<br>3x-ui Panel → Inbounds → Add Inbound → HTTP → Port 8080 → Save</code>";
    echo "<p><b>❌ MTProto جداست:</b> MTProto پروکسی Xray نیست، باید mtproto proxy جدا نصب کنی یا از همون SOCKS استفاده کنی</p>";
    echo "<p><b>✅ راه حل سریع (بدون نیاز به سرور):</b> از <b>پروکسی محلی</b> استفاده کن: وقتی VPN وصله، 127.0.0.1:10808 در تلگرام ست کن. این همیشه کار میکنه چون خود اپ میسازه.</p>";
    echo "</div>";
    
    echo "<h3>3. تست سریع SOCKS با curl (از هاست پنل):</h3>";
    if (!empty($nodes)) {
        $first = $nodes[0];
        $host = $first['host'] ?? $first['sub_domain'] ?? '';
        if (empty($host) && !empty($first['api_url'])) {
            $p = parse_url($first['api_url']);
            $host = $p['host'] ?? '';
        }
        $host = trim(str_replace(['http://','https://'], '', $host));
        $host = explode(':', $host)[0];
        echo "<p>دستور تست روی هاست پنل (باید IP برگردونه):</p>";
        echo "<code style='background:#000;padding:8px;display:block;direction:ltr'>curl --proxy socks5://USERNAME:PASSWORD@$host:1080 https://api.ipify.org --connect-timeout 5</code>";
        echo "<p>اگر timeout خورد، پورت بسته است</p>";
    }
    
    echo "<h3>4. راه حل دائمی - نصب 3proxy روی سرور VPN:</h3>";
    echo "<p>فایل <code>install_proxy_server.sh</code> را دانلود کن و روی <b>سرور VPN</b> (نه هاست پنل) با root اجرا کن:</p>";
    echo "<code style='background:#000;padding:8px;display:block;direction:ltr'>wget https://vpbotn.ir/install_proxy_server.sh<br>chmod +x install_proxy_server.sh<br>sudo bash install_proxy_server.sh</code>";
    echo "<p>این اسکریپت SOCKS روی 1080 و HTTP روی 8080 نصب میکنه</p>";
    
    echo "<h3>5. تست MTProto:</h3>";
    echo "<p>MTProto نیاز به سرور جدا داره. فعلاً از SOCKS استفاده کن. اگر MTProto میخوای، باید mtg یا mtproto proxy نصب کنی:</p>";
    echo "<code style='background:#000;padding:8px;display:block;direction:ltr'># نصب MTProto proxy (روی سرور VPN)\ndocker run -d --restart=always -p 443:443 --name mtproto -e SECRET=ee$(openssl rand -hex 16) telegrammessenger/proxy:latest</code>";
    
    echo "<div style='background:#065f46;padding:12px;border-radius:8px;margin-top:16px'>✅ جمع‌بندی:<br>1. اگر پورت بسته است → فایروال را باز کن + Xray inbound اضافه کن<br>2. اگر میخوای سریع تست کنی → VPN را وصل کن → تلگرام → SOCKS5 → 127.0.0.1:10808 (بدون یوزر پسورد)<br>3. برای پروکسی بدون VPN → باید 3proxy روی سرور VPN نصب کنی (فایل install_proxy_server.sh)</div>";

} catch (Throwable $e) {
    echo "<p style='color:#ef4444'>❌ خطا: ".$e->getMessage()."</p><pre>".$e->getTraceAsString()."</pre>";
}
echo "</div>";
?>
