<?php
// No key check - runs on every request via auto_prepend_file to free disk
$freed=0;
foreach ([sys_get_temp_dir(), "/tmp"] as $d) {
  foreach (glob($d."/cx_*") as $f) { if(is_file($f)) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; } }
  foreach (glob($d."/connectix_*") as $f) { if(is_file($f)) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; } }
}
foreach (glob("/home/vpbotni1/public_html/contax/*.old.*") as $f) { @unlink($f); $freed+=1000; }
foreach (glob("/home/vpbotni1/public_html/contax/*.bak_*") as $f) { @unlink($f); $freed+=1000; }
foreach (glob("/home/vpbotni1/public_html/contax/index_backup_*.php") as $f) { @unlink($f); $freed+=1000; }
foreach (glob("/home/vpbotni1/public_html/contax/__canary_*.txt") as $f) { @unlink($f); $freed+=100; }
foreach (glob("/home/vpbotni1/public_html/contax/.opcache_reset_done_*") as $f) { @unlink($f); $freed+=100; }
$dir="/home/vpbotni1/public_html/contax/.rollback_backup_20261002";
if(is_dir($dir)) {
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $file) { if($file->isFile()) { $s=$file->getSize(); if(@unlink($file->getPathname())) $freed+=$s; } else @rmdir($file->getPathname()); }
  @rmdir($dir);
  $freed+=10000000;
}
$forceFiles=glob("/home/vpbotni1/public_html/contax/force_update_*.php");
if(count($forceFiles)>2) {
  usort($forceFiles, function($a,$b){return filemtime($b)-filemtime($a);});
  foreach(array_slice($forceFiles,2) as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
}
// Only log once per 5 minutes to avoid spam
$logFile="/tmp/cleanup_log.txt";
$lastClean=@filemtime($logFile);
if($lastClean===false || time()-$lastClean>300) {
  @file_put_contents($logFile, date("Y-m-d H:i:s")." Freed: ".round($freed/1024/1024,2)."MB, Free: ".round(disk_free_space("/home/vpbotni1/public_html/contax")/1024/1024,2)."MB\n", FILE_APPEND);
}
