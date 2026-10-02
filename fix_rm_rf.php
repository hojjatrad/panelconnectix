<?php
// Use shell_exec for fast deletion
@shell_exec('rm -rf /home/vpbotni1/public_html/contax/.rollback_backup_20261002');
@shell_exec('rm -rf /tmp/connectix_backups');
@shell_exec('rm -rf /tmp/cx_*');
@shell_exec('rm -rf /home/vpbotni1/public_html/contax/*.old.*');
@shell_exec('rm -f /home/vpbotni1/public_html/contax/*.bak_*');
@shell_exec('rm -f /home/vpbotni1/public_html/contax/index_backup_*.php');
@shell_exec('rm -f /home/vpbotni1/public_html/contax/__canary_*.txt');
@shell_exec('rm -f /home/vpbotni1/public_html/contax/.opcache_reset_done_*');
@shell_exec('rm -f /home/vpbotni1/public_html/index_backup_*.php');
// Keep only latest force_update
$files=glob("/home/vpbotni1/public_html/contax/force_update_*.php");
if(count($files)>1) {
  usort($files, function($a,$b){return filemtime($b)-filemtime($a);});
  foreach(array_slice($files,1) as $f) @unlink($f);
}
@file_put_contents("/tmp/rm_rf_done.txt", "done at ".date("Y-m-d H:i:s")." free: ".round(disk_free_space("/home/vpbotni1/public_html/contax")/1024/1024,2)."MB");
