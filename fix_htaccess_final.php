<?php
// Fix .htaccess to prevent Cloudflare caching dashboard with banner
$htaccessContent = file_get_contents(__DIR__ . '/.htaccess');
if (empty($htaccessContent)) {
    // Fallback: fetch from tmpfiles or use embedded
    $htaccessContent = <<<HT
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>

<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" env=HTTPS
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://*.telegram.org https://telegram.org; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https: blob:; connect-src 'self' https://api.telegram.org wss: https:; frame-src https://*.telegram.org https://telegram.org; object-src 'none'; base-uri 'self'; form-action 'self'"
    Header unset ETag
    FileETag None
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "(quick_update|repair|cron|update_to_|update_apks).php$">
        Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0, no-transform"
        Header set Pragma "no-cache"
        Header set Expires "0"
        Header set cf-cache-status "BYPASS"
        Header set CDN-Cache-Control "no-store"
        Header set Cloudflare-CDN-Cache-Control "no-store, max-age=0"
        Header set X-Accel-Buffering "no"
    </FilesMatch>
    <FilesMatch "\.apk$">
        Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0, no-transform, private"
        Header set Pragma "no-cache"
        Header set Expires "0"
        Header set cf-cache-status "BYPASS"
        Header set CDN-Cache-Control "no-store, max-age=0"
        Header set Cloudflare-CDN-Cache-Control "no-store, max-age=0"
        Header set X-Content-Type-Options "nosniff"
        Header set X-Accel-Buffering "no"
        Header set Vary "Accept-Encoding"
    </FilesMatch>
    <FilesMatch "app_release\.json$">
        Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
        Header set cf-cache-status "BYPASS"
        Header set CDN-Cache-Control "no-store"
    </FilesMatch>
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType application/vnd.android.package-archive "access plus 0 seconds"
    ExpiresByType application/json "access plus 0 seconds"
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^core/.*\.php$ - [F,L]
    RewriteRule ^cache/.* - [F,L]
    RewriteRule ^data/.* - [F,L]
    RewriteRule ^(config\.php|.*\.sqlite.*)$ - [F,L]
</IfModule>

<FilesMatch "^(config\.php|\.env|\.git)">
    Require all denied
</FilesMatch>
<IfModule mod_authz_core.c>
    <FilesMatch "^\.ht">
        Require all denied
    </FilesMatch>
</IfModule>

<FilesMatch "\.(sqlite|sqlite-journal|sqlite-wal|sqlite-shm|log)$">
    Require all denied
</FilesMatch>

<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
    AddOutputFilterByType DEFLATE application/xml application/xhtml+xml application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript application/x-javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE application/vnd.ms-fontobject application/x-font-ttf font/opentype image/svg+xml image/x-icon
    SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|webp|woff2?)$ no-gzip dont-vary
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType application/x-javascript "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/ttf "access plus 1 year"
    ExpiresByType application/font-woff "access plus 1 year"
    ExpiresByType application/font-woff2 "access plus 1 year"
    ExpiresByType text/html "access plus 0 seconds"
    ExpiresByType application/json "access plus 0 seconds"
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "\.(css|js|webp|svg|png|jpg|jpeg|gif|ico|woff2?|ttf)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    <FilesMatch "\.php$">
        Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0, no-transform, private"
        Header set Pragma "no-cache"
        Header set Expires "0"
        Header set cf-cache-status "BYPASS"
        Header set CDN-Cache-Control "no-store, max-age=0"
        Header set Cloudflare-CDN-Cache-Control "no-store, max-age=0"
        Header set X-Accel-Buffering "no"
        Header set Vary "Cookie, Accept-Encoding"
    </FilesMatch>
    <FilesMatch "\.html$">
        Header set Cache-Control "private, no-cache, no-store, must-revalidate"
    </FilesMatch>
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "^(index\.php)?$">
        Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0, no-transform, private"
        Header set Pragma "no-cache"
        Header set Expires "0"
        Header set cf-cache-status "BYPASS"
        Header set CDN-Cache-Control "no-store"
        Header set Cloudflare-CDN-Cache-Control "no-store"
    </FilesMatch>
</IfModule>

<FilesMatch "^\.">
    Require all denied
</FilesMatch>

Options -Indexes
HT;
}

file_put_contents(__DIR__ . '/.htaccess', $htaccessContent);
echo "✅ .htaccess fixed with BYPASS for all PHP\n";
echo "Now purge Cloudflare cache manually: Cloudflare Dashboard -> Caching -> Purge Everything\n";
