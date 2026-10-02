<?php
echo "Redis extension: ".(extension_loaded('redis')?'YES':'NO')."\n";
echo "Memcached: ".(extension_loaded('memcached')?'YES':'NO')."\n";
echo "APCu: ".(extension_loaded('apcu')?'YES':'NO')."\n";
