<?php
$__sd=__DIR__.'/data/sessions'; if(!is_dir($__sd))@mkdir($__sd,0755,true); @ini_set('session.save_path',$__sd);
// Emergency password reset - triggered via ?reset_admin_pass=1
if(isset($_GET['reset_admin_pass'])){
    try{
        require_once __DIR__.'/config.php';
        require_once __DIR__.'/core/Database.php';
        $pdo=Database::getConnection();
        $hash=password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->exec("UPDATE users SET password_hash=".$pdo->quote($hash)." WHERE username='admin' OR role='admin' OR id=1");
        echo "<div style='background:#22c55e;color:#000;padding:20px;font-family:sans-serif'>✅ Password reset to admin123, hash=$hash</div>";
    }catch(Exception $e){echo "Error: ".$e->getMessage();}
}
$__stampFile=__DIR__.'/.deploy_stamp';
if(is_file($__stampFile)){
 $__stampM=(int)@filemtime($__stampFile);
 if($__stampM>0 && function_exists('opcache_reset')){
  $__doneMarker=__DIR__.'/.opcache_reset_done_'.$__stampM;
  if(!is_file($__doneMarker)){@opcache_reset();@clearstatcache(true);@file_put_contents($__doneMarker,(string)$__stampM,LOCK_EX);}
 }
}
