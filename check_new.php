<?php
$file = __DIR__.'/core/Performance.php';
echo "Exists: ".(file_exists($file)?'yes':'no')."\n";
echo "Size: ".filesize($file)."\n";
echo "MD5: ".md5_file($file)."\n";
echo "Contains via main PDO: ".(strpos(file_get_contents($file),'via main PDO')!==false?'YES':'NO')."\n";
echo "Contains separate connection: ".(strpos(file_get_contents($file),'separate connection')!==false?'YES':'NO')."\n";
echo substr(file_get_contents($file), 2000, 500);
