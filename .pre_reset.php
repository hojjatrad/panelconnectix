<?php
$__sessionDir = __DIR__ . '/data/sessions';
if (!is_dir($__sessionDir)) {@mkdir($__sessionDir,0755,true);}
if (is_dir($__sessionDir) && is_writable($__sessionDir)) {
    @ini_set('session.save_path', $__sessionDir);
}
$__rf = __DIR__ . '/reset_admin.php';
$__log = __DIR__ . '/data/tmp/pre_reset_debug.txt';
@mkdir(__DIR__.'/data/tmp',0755,true);
$__msg = date('Y-m-d H:i:s')." CHECK rf exists=". (is_file($__rf)?'YES':'NO') . " writable dir=". (is_writable(__DIR__)?'YES':'NO') . "\n";
if (!is_file($__rf)) {
    $__content = '<?php echo "RESET ADMIN WORKS"; ?>';
    $__r = @file_put_contents($__rf, $__content);
    $__msg .= " TRY WRITE r=".var_export($__r,true)." error=".error_get_last()['message']."\n";
    $__msg .= " AFTER exists=". (is_file($__rf)?'YES':'NO') . " size=". (@filesize($__rf)?:0) . "\n";
} else {
    $__msg .= " ALREADY EXISTS size=".filesize($__rf)."\n";
}
@file_put_contents($__log, $__msg, FILE_APPEND);
unset($__rf,$__log,$__msg,$__content,$__r);
$__stampFile=__DIR__.'/.deploy_stamp';
if(is_file($__stampFile)){
 $__stampM=(int)@filemtime($__stampFile);
 if($__stampM>0 && function_exists('opcache_reset')){
  $__doneMarker=__DIR__.'/.opcache_reset_done_'.$__stampM;
  if(!is_file($__doneMarker)){@opcache_reset();@clearstatcache(true);@file_put_contents($__doneMarker,(string)$__stampM,LOCK_EX);}
 }
}
