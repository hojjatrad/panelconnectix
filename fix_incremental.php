<?php
// Incremental cleanup - deletes 20 files per request to avoid timeout
$base="/home/vpbotni1/public_html/contax/.rollback_backup_20261002";
if(is_dir($base)) {
  $count=0;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $file) {
    if($count>=20) break;
    if($file->isFile()) { @unlink($file->getPathname()); $count++; }
    else { @rmdir($file->getPathname()); $count++; }
  }
  if($count==0) @rmdir($base); // Try to remove dir if empty
}
// Also clean tmp
foreach (glob("/tmp/cx_*") as $f) { @unlink($f); }
foreach (glob("/tmp/connectix_*") as $f) { if(is_file($f)) @unlink($f); else { @shell_exec("rm -rf ".escapeshellarg($f)); } }
// Clean force_update keep only 1
$forceFiles=glob("/home/vpbotni1/public_html/contax/force_update_*.php");
if(count($forceFiles)>1) {
  usort($forceFiles, function($a,$b){return filemtime($b)-filemtime($a);});
  @unlink($forceFiles[count($forceFiles)-1]); // Delete oldest
}
