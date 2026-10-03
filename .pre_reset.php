<?php
$__sd=__DIR__.'/data/sessions'; if(!is_dir($__sd))@mkdir($__sd,0755,true); @ini_set('session.save_path',$__sd);
@unlink(__DIR__.'/repair.php');
