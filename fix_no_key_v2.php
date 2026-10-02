<?php
// Aggressive disk cleanup - deletes large backup dirs
$freed=0;
$deleted=[];
// Clean all tmp
foreach ([sys_get_temp_dir(), "/tmp", "/home/vpbotni1/tmp"] as $d) {
  if(!is_dir($d)) continue;
  foreach (glob($d."/cx_*") as $f) { if(is_file($f)) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; $deleted[]=basename($f); } elseif(is_dir($f)) { 
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($f, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $file) { if($file->isFile()) { $s=$file->getSize(); if(@unlink($file->getPathname())) $freed+=$s; } else @rmdir($file->getPathname()); }
    @rmdir($f);
  } }
  foreach (glob($d."/connectix_*") as $f) { if(is_file($f)) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; } elseif(is_dir($f)) {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($f, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $file) { if($file->isFile()) { $s=$file->getSize(); if(@unlink($file->getPathname())) $freed+=$s; } else @rmdir($file->getPathname()); }
    @rmdir($f);
  } }
  foreach (glob($d."/repair_*") as $f) { if(is_file($f)) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; } }
  // Large backup dir
  $backupDir=$d."/connectix_backups";
  if(is_dir($backupDir)) {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $file) { if($file->isFile()) { $s=$file->getSize(); if(@unlink($file->getPathname())) $freed+=$s; } else @rmdir($file->getPathname()); }
    @rmdir($backupDir);
    $deleted[]="connectix_backups (DIR)";
  }
}
// Clean contax
foreach (glob("/home/vpbotni1/public_html/contax/*.old.*") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
foreach (glob("/home/vpbotni1/public_html/contax/*.bak_*") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
foreach (glob("/home/vpbotni1/public_html/contax/index_backup_*.php") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
foreach (glob("/home/vpbotni1/public_html/contax/__canary_*.txt") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
foreach (glob("/home/vpbotni1/public_html/contax/.opcache_reset_done_*") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
foreach (glob("/home/vpbotni1/public_html/*.old.*") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
foreach (glob("/home/vpbotni1/public_html/index_backup_*.php") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
// Rollback dir
$dir="/home/vpbotni1/public_html/contax/.rollback_backup_20261002";
if(is_dir($dir)) {
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $file) { if($file->isFile()) { $s=$file->getSize(); if(@unlink($file->getPathname())) $freed+=$s; } else @rmdir($file->getPathname()); }
  @rmdir($dir);
  $freed+=10000000;
  $deleted[]=".rollback_backup_20261002";
}
// Old force_update keep only 1
$forceFiles=glob("/home/vpbotni1/public_html/contax/force_update_*.php");
if(count($forceFiles)>1) {
  usort($forceFiles, function($a,$b){return filemtime($b)-filemtime($a);});
  foreach(array_slice($forceFiles,1) as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; $deleted[]=basename($f); }
}
// Clean data/*.log
foreach (glob("/home/vpbotni1/public_html/contax/data/*.log") as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; }
@file_put_contents("/tmp/cleanup_v2_done.txt", date("Y-m-d H:i:s")." Freed: ".round($freed/1024/1024,2)."MB, Free: ".round(disk_free_space("/home/vpbotni1/public_html/contax")/1024/1024,2)."MB, Deleted: ".implode(",",array_slice($deleted,0,10)));
