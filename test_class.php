<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/controllers/ApiController.php';

$ref = new ReflectionClass('ApiController');
echo "ApiController file: " . $ref->getFileName() . "\n";
$m = $ref->getMethod('appConfigs');
echo "appConfigs lines: " . $m->getStartLine() . " to " . $m->getEndLine() . "\n";

$lines = file($ref->getFileName());
for ($i = $m->getStartLine() - 1; $i < $m->getEndLine(); $i++) {
    echo $lines[$i];
}
