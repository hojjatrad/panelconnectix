<?php
$f = __DIR__.'/core/Performance.php';
echo "Size: ".filesize($f)." MD5: ".md5_file($f)."\n";
echo "Contains fresh PDO for all: ".(strpos(file_get_contents($f),'fresh PDO for all')!==false?'YES':'NO')."\n";
