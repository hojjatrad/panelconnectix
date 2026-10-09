<?php
header('Content-Type: text/plain; charset=utf-8');
echo "=== CLEAR TMP ===\n";
$dirs = [__DIR__.'/data/tmp', __DIR__.'/data/sessions', sys_get_temp_dir(), '/tmp'];
foreach ($dirs as $d) {
    echo "Dir $d\n";
    if (!is_dir($d)) { echo "  not dir\n"; continue; }
    $files = @glob($d.'/*');
    if (!$files) { echo "  no files\n"; continue; }
    $cnt=0; $freed=0;
    foreach ($files as $f) {
        if (is_file($f) && (strpos(basename($f),'browser_update')!==false || strpos(basename($f),'connectix_update')!==false || strpos(basename($f),'panel_main')!==false || filemtime($f) < time()-1800)) {
            $sz=@filesize($f);
            if (@unlink($f)) { $cnt++; $freed+=$sz; }
        }
    }
    echo "  Deleted $cnt freed ".round($freed/1024/1024,2)." MB\n";
    echo "  Free: ".round(@disk_free_space($d)/1024/1024,2)." MB\n";
}
echo "\n=== FIX440 ===\n";
echo file_exists(__DIR__.'/fix_440_simple.php') ? "exists ".filesize(__DIR__.'/fix_440_simple.php')." bytes\n" : "not exists\n";
echo "Done\n";
