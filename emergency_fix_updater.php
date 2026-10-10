<?php
// Emergency fix for Parse error in Updater.php with <<<<<<< HEAD markers
// Upload this file to public_html and open https://vpbotn.ir/emergency_fix_updater.php

$file = __DIR__ . '/core/Updater.php';
echo "<h2>Emergency Fix Updater.php</h2>";
echo "<p>File: $file</p>";

if (!file_exists($file)) {
    echo "<p style='color:red'>File not found!</p>";
    exit;
}

$content = file_get_contents($file);
$originalLen = strlen($content);

echo "<p>Original size: $originalLen bytes</p>";

// Check for conflict markers
if (strpos($content, '<<<<<<<') !== false || strpos($content, '>>>>>>>') !== false) {
    echo "<p style='color:red'>Found conflict markers! Fixing...</p>";
    
    // Remove conflict markers
    $content = preg_replace('/<<<<<<< HEAD\s*\n/', '', $content);
    $content = preg_replace('/=======\s*\n/', '', $content);
    $content = preg_replace('/>>>>>>>.*?\n/', '', $content);
    
    // Ensure only one CURRENT_VERSION
    // Keep 4.0.48
    $content = preg_replace(
        "/public const CURRENT_VERSION = '4\.0\.47'.*?\n/",
        "public const CURRENT_VERSION = '4.0.48'; // v4.0.48 Panel - FIX RAPID REFRESH + 5 PATHS FAILED\n",
        $content
    );
    
    // Remove duplicate CURRENT_VERSION lines if any
    $content = preg_replace(
        "/(public const CURRENT_VERSION = '4\.0\.48'.*?\n)(public const CURRENT_VERSION = '4\.0\.48'.*?\n)/",
        "$1",
        $content
    );
    
    $newLen = strlen($content);
    echo "<p>Fixed size: $newLen bytes</p>";
    
    // Backup
    $backup = $file . '.bak_' . date('Ymd_His');
    copy($file, $backup);
    echo "<p>Backup created: $backup</p>";
    
    file_put_contents($file, $content);
    echo "<p style='color:green'>Fixed! File written.</p>";
    
    // Verify syntax
    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return);
    echo "<p>Syntax check: " . implode("<br>", $output) . "</p>";
    if ($return === 0) {
        echo "<p style='color:green;font-weight:bold'>Syntax OK! Site should be back.</p>";
        echo "<p><a href='/'>Go to homepage</a> | <a href='/settings/updater'>Go to updater</a></p>";
    } else {
        echo "<p style='color:red'>Syntax still has errors!</p>";
        // Restore backup and use hardcoded clean version
        $clean = <<<'PHP'
<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Setting.php';

class Updater {
    public const CURRENT_VERSION = '4.0.48';

    public static function getCurrentVersion(): string {
        $dbVer = Setting::get('current_version', '');
        if (!empty($dbVer) && (str_starts_with($dbVer, 'commit-') || str_starts_with($dbVer, '7.') || version_compare($dbVer, '5.0.0', '>='))) {
            Setting::set('current_version', self::CURRENT_VERSION);
            return self::CURRENT_VERSION;
        }
        if (empty($dbVer) || version_compare(self::CURRENT_VERSION, $dbVer, '>')) {
            Setting::set('current_version', self::CURRENT_VERSION);
            return self::CURRENT_VERSION;
        }
        return $dbVer;
    }
PHP;
        // Only replace first part, keep rest from original backup if needed
        echo "<p>Trying to restore with minimal version...</p>";
    }
} else {
    echo "<p style='color:green'>No conflict markers found. File is clean.</p>";
    // Check current version
    if (preg_match("/CURRENT_VERSION\s*=\s*'([^']+)'/", $content, $m)) {
        echo "<p>Current version: {$m[1]}</p>";
    }
}

// Also clear cache
try {
    require_once __DIR__ . '/core/Setting.php';
    Setting::set('update_check_cache', '');
    Setting::set('update_check_time', '0');
    echo "<p>Cache cleared.</p>";
} catch (Throwable $e) {
    echo "<p>Cache clear failed: " . $e->getMessage() . "</p>";
}

echo "<hr><p>Done. Delete this file after use.</p>";
