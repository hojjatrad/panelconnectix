<?php
$file = __DIR__.'/optimize_performance.php';
echo "Size: ".filesize($file)."\n";
echo "Contains via main PDO: ".(strpos(file_get_contents($file),'via main PDO')!==false?'YES':'NO')."\n";
echo "MD5: ".md5_file($file)."\n";
echo substr(file_get_contents($file),0,1000);
