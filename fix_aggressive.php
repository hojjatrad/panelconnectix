<?php
// Ultra aggressive cleanup - deletes ALL large files
$freed=0;
$log="";
// Check home dir for large files
foreach (["/home/vpbotni1", "/home/vpbotni1/public_html", "/home/vpbotni1/public_html/contax", "/tmp"] as $base) {
  if(!is_dir($base)) continue;
  $files=glob($base."/*");
  foreach($files as $f) {
    if(is_file($f)) {
      $size=filesize($f);
      // Delete files > 1MB that are not essential
      if($size>1024*1024) {
        $ext=strtolower(pathinfo($f, PATHINFO_EXTENSION));
        // Keep only essential files, delete backups, zips, logs, old
        if(in_array($ext, ["zip","tar","gz","log","bak","old","sql","apk"]) || strpos(basename($f), "backup")!==false || strpos(basename($f), "force_update")!==false || strpos(basename($f), "index_backup")!==false) {
          if(@unlink($f)) { $freed+=$size; $log.=basename($f)." ".round($size/1024/1024,2)."MB deleted\n"; }
        }
      }
    }
  }
  // Check hidden files
  foreach (glob($base."/.*") as $f) {
    if(is_file($f) && filesize($f)>1024*1024) {
      $size=filesize($f);
      if(@unlink($f)) { $freed+=$size; $log.=basename($f)." ".round($size/1024/1024,2)."MB deleted\n"; }
    }
  }
}
// Delete specific large dirs
foreach (["/home/vpbotni1/public_html/contax/.rollback_backup_20261002", "/tmp/connectix_backups", "/home/vpbotni1/tmp"] as $dir) {
  if(is_dir($dir)) {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $file) { 
      if($file->isFile()) { $s=$file->getSize(); if(@unlink($file->getPathname())) $freed+=$s; } 
      else @rmdir($file->getPathname()); 
    }
    @rmdir($dir);
    $log.="$dir deleted\n";
  }
}
// Delete all force_update keep only 1 latest
$forceFiles=glob("/home/vpbotni1/public_html/contax/force_update_*.php");
if(count($forceFiles)>1) {
  usort($forceFiles, function($a,$b){return filemtime($b)-filemtime($a);});
  foreach(array_slice($forceFiles,1) as $f) { $s=@filesize($f); if(@unlink($f)) $freed+=$s; $log.=basename($f)." deleted\n"; }
}
@file_put_contents("/tmp/aggressive_cleanup.log", date("Y-m-d H:i:s")." Freed: ".round($freed/1024/1024,2)."MB\n".$log);
@file_put_contents("/home/vpbotni1/public_html/contax/assets/cleanup.log", date("Y-m-d H:i:s")." Freed: ".round($freed/1024/1024,2)."MB\n".$log);
