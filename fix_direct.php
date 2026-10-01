<?php
// Direct fix for Performance.php on live - bypasses GitHub zip cache
$code = file_get_contents('/home/user/connectix-panel/core/Performance.php');
echo "Local length: ".strlen($code)."\n";
echo substr($code,0,200)."\n";
