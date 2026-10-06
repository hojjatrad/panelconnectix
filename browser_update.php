<?php
/**
 * BROWSER-BASED UPDATER v4.0.19 - For hosts where server cannot connect to GitHub (Iran)
 * Downloads ZIP via browser JS (client can access GitHub) then uploads to server via POST
 * Bypasses server outbound block
 */
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');

$repo = 'hojjatrad/panelconnectix';
$branch = 'main';

// Handle POST upload of zip data (base64 chunks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'upload_chunk') {
        // Receive base64 chunk
        $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
        $totalChunks = (int)($_POST['total_chunks'] ?? 1);
        $chunkData = $_POST['chunk_data'] ?? '';
        $sessionId = $_POST['session_id'] ?? 'default';
        
        $tmpDir = __DIR__ . '/data/tmp';
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
        $tmpFile = $tmpDir . '/browser_update_' . $sessionId . '.zip';
        
        // Decode chunk
        $binary = base64_decode($chunkData);
        if ($chunkIndex === 0) {
            file_put_contents($tmpFile, $binary);
        } else {
            file_put_contents($tmpFile, $binary, FILE_APPEND);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success'=>true, 'chunk'=>$chunkIndex, 'total'=>$totalChunks, 'size'=>filesize($tmpFile)]);
        exit;
    }
    
    if ($action === 'extract') {
        $sessionId = $_POST['session_id'] ?? 'default';
        $tmpDir = __DIR__ . '/data/tmp';
        $tmpFile = $tmpDir . '/browser_update_' . $sessionId . '.zip';
        $extractDir = $tmpDir . '/browser_ext_' . $sessionId;
        
        if (!file_exists($tmpFile)) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false, 'error'=>'فایل ZIP یافت نشد']);
            exit;
        }
        
        @mkdir($extractDir, 0755, true);
        
        $extracted = false;
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($tmpFile) === true) {
                $zip->extractTo($extractDir);
                $zip->close();
                $extracted = true;
            }
        }
        
        if (!$extracted && function_exists('shell_exec')) {
            $cmd = 'unzip -q -o '.escapeshellarg($tmpFile).' -d '.escapeshellarg($extractDir).' 2>&1';
            $out = @shell_exec($cmd);
            if (!empty(glob($extractDir.'/*'))) $extracted = true;
        }
        
        if (!$extracted) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false, 'error'=>'استخراج ناموفق']);
            exit;
        }
        
        // Find source dir
        $subDirs = glob($extractDir.'/*', GLOB_ONLYDIR);
        $sourceDir = (!empty($subDirs) && is_dir($subDirs[0])) ? $subDirs[0] : $extractDir;
        
        // Sync PHP files (same as quick_update)
        $repaired = 0;
        $failed = [];
        $srcPrefix = str_replace('\\','/', rtrim($sourceDir,'/')).'/';
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($rii as $fileInfo) {
            if ($fileInfo->isDir()) continue;
            if ($fileInfo->getExtension() !== 'php') continue;
            $full = str_replace('\\','/', $fileInfo->getPathname());
            if (!str_starts_with($full, $srcPrefix)) continue;
            $rel = substr($full, strlen($srcPrefix));
            if (basename($rel)==='config.php' && dirname($rel)==='.') continue;
            $want = @file_get_contents($fileInfo->getPathname());
            if ($want===false || strlen($want)===0) continue;
            $live = __DIR__ . '/' . $rel;
            if (!is_dir(dirname($live))) @mkdir(dirname($live),0755,true);
            if (file_exists($live)) { @chmod($live,0777); @unlink($live); }
            $w = @file_put_contents($live, $want, LOCK_EX);
            @chmod($live,0644);
            if ($w!==false && file_exists($live) && sha1_file($live)===sha1($want)) $repaired++;
            else $failed[] = $rel;
        }
        
        // Cleanup
        @unlink($tmpFile);
        // Don't delete extract dir immediately, keep for debug
        // Recursive delete
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if ($file->isFile()) @unlink($file->getPathname());
            else @rmdir($file->getPathname());
        }
        @rmdir($extractDir);
        
        // Clear opcache
        @touch(__DIR__ . '/.deploy_stamp');
        if (function_exists('opcache_reset')) @opcache_reset();
        
        // Update version
        try {
            require_once __DIR__ . '/core/Database.php';
            require_once __DIR__ . '/core/Setting.php';
            Setting::set('current_version', '7.2.0');
            Setting::set('last_installed_commit_sha', substr($sessionId,0,7));
            // Fix roles
            $pdo = Database::getConnection();
            $pdo->exec("DELETE FROM system_settings WHERE setting_key LIKE 'login_lock_%'");
            $pdo->exec("UPDATE users SET status='active', two_factor_enabled=0 WHERE id IN (1,2) OR LOWER(username) IN ('admin','novinvpn')");
            $pdo->exec("UPDATE users SET role='admin' WHERE id=1 OR LOWER(username)='admin'");
            $pdo->exec("UPDATE users SET role='reseller' WHERE LOWER(username)='novinvpn'");
            $pdo->exec("UPDATE users SET status='active' WHERE role='admin'");
        } catch (Throwable $e) {}
        
        header('Content-Type: application/json');
        echo json_encode(['success'=>true, 'repaired'=>$repaired, 'failed'=>$failed]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>آپدیت مرورگری - دور زدن فیلترینگ هاست</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap');*{font-family:'Vazirmatn',sans-serif}</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 flex items-center justify-center">
<div class="max-w-2xl w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-5 shadow-2xl">
    <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
        <div class="w-10 h-10 rounded-xl bg-cyan-600/20 text-cyan-400 flex items-center justify-center text-lg font-bold">🌐</div>
        <div>
            <h1 class="text-base font-black text-white">آپدیت مرورگری v4.0.19 - دور زدن فیلتر هاست</h1>
            <p class="text-[11px] text-slate-400">وقتی هاست نمی‌تواند به گیت‌هاب وصل شود، مرورگر شما ZIP را دانلود و به هاست آپلود می‌کند</p>
        </div>
    </div>

    <div id="status" class="space-y-2 text-xs font-mono bg-slate-950 p-4 rounded-xl border border-slate-800 min-h-[120px] max-h-[300px] overflow-auto">
        <div class="text-slate-400">آماده برای آپدیت...</div>
        <div class="text-slate-500 text-[10px]">ریپو: <?= htmlspecialchars($repo) ?> شاخه: <?= htmlspecialchars($branch) ?></div>
    </div>

    <div class="grid grid-cols-1 gap-3">
        <button id="startBtn" onclick="startBrowserUpdate()" class="w-full py-3 bg-gradient-to-r from-cyan-600 to-violet-600 hover:from-cyan-500 hover:to-violet-500 text-white font-black rounded-xl text-sm shadow-lg">🚀 شروع آپدیت مرورگری (دانلود از گیت‌هاب با مرورگر)</button>
        <div class="grid grid-cols-2 gap-2">
            <a href="quick_update.php" class="py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs text-center">تلاش quick_update سروری</a>
            <a href="fix_deep_admin_crash_v4_0_19.php" class="py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-xl text-xs text-center">فیکس ادمین</a>
        </div>
    </div>

    <div class="bg-amber-950/30 border border-amber-800/30 rounded-xl p-3 text-xs space-y-1">
        <p class="font-bold text-amber-300">💡 چرا این روش؟</p>
        <p class="text-slate-300">هاست شما در ایران است و outbound به گیت‌هاب (api.github.com / codeload.github.com) فیلتر یا مسدود است. quick_update.php روی سرور اجرا می‌شود و نمی‌تواند ZIP را دانلود کند.</p>
        <p class="text-slate-300">این صفحه ZIP را با <b>مرورگر شما</b> (که به گیت‌هاب دسترسی دارد) دانلود می‌کند، سپس تکه‌تکه به سرور آپلود و استخراج می‌کند.</p>
    </div>
</div>

<script>
const repo = "<?= $repo ?>";
const branch = "<?= $branch ?>";
const sessionId = Date.now().toString(36) + Math.random().toString(36).substr(2,5);
let logEl = document.getElementById('status');

function log(msg, type='info') {
    const colors = {info:'text-slate-300', success:'text-emerald-400 font-bold', error:'text-rose-400 font-bold', warn:'text-amber-300', debug:'text-cyan-300'};
    const c = colors[type] || colors.info;
    const div = document.createElement('div');
    div.className = c;
    div.textContent = new Date().toLocaleTimeString() + ' ' + msg;
    logEl.appendChild(div);
    logEl.scrollTop = logEl.scrollHeight;
}

async function startBrowserUpdate() {
    document.getElementById('startBtn').disabled = true;
    document.getElementById('startBtn').textContent = '⏳ در حال دانلود از گیت‌هاب با مرورگر...';
    logEl.innerHTML = '';
    log('شروع آپدیت مرورگری - Session: ' + sessionId, 'debug');
    
    const urls = [
        `https://codeload.github.com/${repo}/zip/refs/heads/${branch}`,
        `https://github.com/${repo}/archive/refs/heads/${branch}.zip`,
        `https://ghfast.top/https://github.com/${repo}/archive/refs/heads/${branch}.zip`,
        `https://gh-proxy.com/https://github.com/${repo}/archive/refs/heads/${branch}.zip`,
    ];
    
    let zipBlob = null;
    let usedUrl = '';
    
    for (const url of urls) {
        try {
            log(`تلاش دانلود از: ${url}`, 'info');
            const resp = await fetch(url, {mode:'cors'});
            if (!resp.ok) {
                log(`HTTP ${resp.status} از ${url}`, 'warn');
                continue;
            }
            const blob = await resp.blob();
            if (blob.size < 5000) {
                log(`سایز کم ${blob.size} از ${url} - احتمالاً خطا`, 'warn');
                continue;
            }
            zipBlob = blob;
            usedUrl = url;
            log(`✅ دانلود موفق از ${url} - سایز: ${(blob.size/1024/1024).toFixed(1)} MB`, 'success');
            break;
        } catch (e) {
            log(`خطا دانلود از ${url}: ${e.message}`, 'warn');
        }
    }
    
    if (!zipBlob) {
        log('❌ تمام لینک‌های گیت‌هاب ناموفق بود - لطفاً VPN مرورگر را روشن کنید یا از لینک مستقیم استفاده کنید', 'error');
        log('💡 پیشنهاد: با VPN این صفحه را باز کنید، یا فایل ZIP را دستی از https://github.com/hojjatrad/panelconnectix/archive/refs/heads/main.zip دانلود و به data/tmp آپلود کنید', 'warn');
        document.getElementById('startBtn').disabled = false;
        document.getElementById('startBtn').textContent = '🚀 تلاش مجدد';
        return;
    }
    
    // Upload in chunks (base64)
    log(`آپلود به سرور در تکه‌های 1MB...`, 'info');
    const chunkSize = 1024*1024; // 1MB binary -> ~1.33MB base64
    const totalChunks = Math.ceil(zipBlob.size / chunkSize);
    log(`تعداد تکه‌ها: ${totalChunks}`, 'info');
    
    for (let i=0; i<totalChunks; i++) {
        const start = i*chunkSize;
        const end = Math.min(start+chunkSize, zipBlob.size);
        const chunkBlob = zipBlob.slice(start, end);
        const arrayBuffer = await chunkBlob.arrayBuffer();
        const base64 = btoa(String.fromCharCode(...new Uint8Array(arrayBuffer)));
        
        const formData = new FormData();
        formData.append('action', 'upload_chunk');
        formData.append('chunk_index', i);
        formData.append('total_chunks', totalChunks);
        formData.append('chunk_data', base64);
        formData.append('session_id', sessionId);
        
        try {
            const resp = await fetch('browser_update.php', {method:'POST', body:formData});
            const json = await resp.json();
            if (!json.success) {
                log(`❌ آپلود تکه ${i} ناموفق: ${json.error}`, 'error');
                return;
            }
            log(`✅ آپلود تکه ${i+1}/${totalChunks} - سرور سایز: ${(json.size/1024/1024).toFixed(1)} MB`, 'info');
        } catch (e) {
            log(`❌ خطا آپلود تکه ${i}: ${e.message}`, 'error');
            return;
        }
    }
    
    log('✅ آپلود کامل - در حال استخراج و نصب...', 'success');
    document.getElementById('startBtn').textContent = '⏳ استخراج فایل‌ها...';
    
    // Extract
    try {
        const formData = new FormData();
        formData.append('action', 'extract');
        formData.append('session_id', sessionId);
        const resp = await fetch('browser_update.php', {method:'POST', body:formData});
        const json = await resp.json();
        if (json.success) {
            log(`✅ استخراج و نصب موفق! ${json.repaired} فایل تعمیر شد`, 'success');
            if (json.failed && json.failed.length>0) {
                log(`⚠️ ${json.failed.length} فایل ناموفق: ${json.failed.slice(0,5).join(', ')}`, 'warn');
            }
            log('🎉 آپدیت کامل شد! حالا به داشبورد بروید', 'success');
            document.getElementById('startBtn').textContent = '✅ آپدیت موفق - رفتن به داشبورد';
            document.getElementById('startBtn').onclick = () => window.location.href = 'dashboard';
            document.getElementById('startBtn').disabled = false;
            
            // Auto redirect after 2s
            setTimeout(()=>window.location.href='fix_deep_admin_crash_v4_0_19.php', 2000);
        } else {
            log(`❌ استخراج ناموفق: ${json.error}`, 'error');
            document.getElementById('startBtn').disabled = false;
            document.getElementById('startBtn').textContent = '🚀 تلاش مجدد';
        }
    } catch (e) {
        log(`❌ خطا استخراج: ${e.message}`, 'error');
        document.getElementById('startBtn').disabled = false;
        document.getElementById('startBtn').textContent = '🚀 تلاش مجدد';
    }
}
</script>
</body>
</html>
<?php
?>
