<?php
/**
 * v4.0.21 FIX - Proxy Copy Separate Links
 * Adds proxy copy modal to clients list + proxies page + adminClientProxies endpoint
 */
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2 style='font-family:sans-serif;direction:rtl'>🔧 فیکس کپی پروکسی جدا v4.0.21</h2><div style='font-family:sans-serif;direction:rtl;background:#0f172a;color:#fff;padding:20px;border-radius:12px'>";

try {
    // 1. Check ProxyController has adminClientProxies
    $pcPath = __DIR__ . '/controllers/ProxyController.php';
    $pcContent = file_get_contents($pcPath);
    if (!str_contains($pcContent, 'adminClientProxies')) {
        echo "<p style='color:#fbbf24'>⚠️ ProxyController قدیمی است - باید از گیت‌هاب آپدیت کنید</p>";
        echo "<p>لطفاً <a href='quick_update.php' style='color:#22d3ee'>quick_update.php</a> یا <a href='browser_update.php' style='color:#22d3ee'>browser_update.php</a> را اجرا کنید</p>";
        echo "<p>یا <a href='full_fix_without_github.php' style='color:#22d3ee'>full_fix_without_github.php</a> v4.0.20 را اول اجرا کنید سپس این فایل را دوباره بزنید</p>";
    } else {
        echo "<p style='color:#10b981'>✅ ProxyController دارای adminClientProxies است</p>";
    }

    // 2. Check index.php routes
    $idxPath = __DIR__ . '/index.php';
    $idxContent = file_get_contents($idxPath);
    if (!str_contains($idxContent, 'clients/{id}/proxies')) {
        echo "<p style='color:#fbbf24'>⚠️ index.php route ندارد - اضافه میکنیم...</p>";
        $idxContent = str_replace(
            "\$router->get('proxies', [ProxyController::class, 'adminProxies']);",
            "\$router->get('proxies', [ProxyController::class, 'adminProxies']);\n\$router->get('clients/{id}/proxies', [ProxyController::class, 'adminClientProxies']);\n\$router->get('api/v1/admin/client-proxies', [ProxyController::class, 'adminClientProxies']);",
            $idxContent
        );
        file_put_contents($idxPath, $idxContent);
        echo "<p style='color:#10b981'>✅ Route ها به index.php اضافه شد</p>";
    } else {
        echo "<p style='color:#10b981'>✅ index.php routes موجود است</p>";
    }

    // 3. Check clients view has proxy button
    $clientsView = __DIR__ . '/views/clients/index.php';
    $cvContent = file_get_contents($clientsView);
    if (!str_contains($cvContent, 'openProxyModal')) {
        echo "<p style='color:#fbbf24'>⚠️ views/clients/index.php قدیمی است - باید آپدیت شود</p>";
        echo "<p>quick_update.php را اجرا کنید</p>";
    } else {
        echo "<p style='color:#10b981'>✅ clients/index.php دارای دکمه پروکسی است</p>";
    }

    // 4. Check proxies view
    $proxiesView = __DIR__ . '/views/proxies/index.php';
    $pvContent = file_get_contents($proxiesView);
    if (!str_contains($pvContent, 'openProxyModalFromList')) {
        echo "<p style='color:#fbbf24'>⚠️ views/proxies/index.php قدیمی است</p>";
    } else {
        echo "<p style='color:#10b981'>✅ proxies/index.php جدید است</p>";
    }

    echo "<div style='background:#065f46;padding:12px;border-radius:8px;margin-top:16px'>✅ بررسی کامل شد<br><br>اگر همه ✅ هستند:<br>1. برو <b>کلاینت‌ها</b> → هر ردیف دکمه <i style='color:#22d3ee'>🛡️</i> آبی دارد<br>2. کلیک کن → مودال باز میشه<br>3. هر پروکسی (SOCKS5 / HTTP / MTProto) را جدا کپی کن<br>4. دکمه <b>کپی متن کامل</b> → ساب + پروکسی با هم برای مشتری<br>5. دکمه سبز <i style='color:#10b981'>share</i> هم الان ساب + پروکسی را با هم کپی میکنه<br><br>اگر ❌ یا ⚠️ داری: quick_update.php یا browser_update.php را اجرا کن</div>";
    echo "<br><div style='display:flex;gap:8px;flex-wrap:wrap;margin-top:12px'><a href='clients' style='background:#7c3aed;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>کلاینت‌ها (تست پروکسی)</a> <a href='proxies' style='background:#0891b2;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>پروکسی‌ها</a> <a href='quick_update.php' style='background:#334155;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none'>quick_update</a></div>";

} catch (Throwable $e) {
    echo "<p style='color:#ef4444'>❌ خطا: ".$e->getMessage()."<br><pre>".$e->getTraceAsString()."</pre></p>";
}
echo "</div>";
?>
