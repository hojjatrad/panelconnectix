<?php
/**
 * O3: WebP + Lazy Load - Image Optimization
 */

class ImageOptimizer {
    /**
     * Convert image to WebP if supported
     */
    public static function convertToWebP(string $sourcePath, int $quality = 80): ?string {
        if (!function_exists('imagewebp')) return null;
        if (!is_file($sourcePath)) return null;
        
        $info = getimagesize($sourcePath);
        if (!$info) return null;
        
        $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $sourcePath);
        if (is_file($webpPath)) return $webpPath; // Already converted
        
        try {
            $image = null;
            if ($info[2] === IMAGETYPE_JPEG) {
                $image = imagecreatefromjpeg($sourcePath);
            } elseif ($info[2] === IMAGETYPE_PNG) {
                $image = imagecreatefrompng($sourcePath);
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
            
            if ($image) {
                imagewebp($image, $webpPath, $quality);
                imagedestroy($image);
                return $webpPath;
            }
        } catch (Throwable $e) {}
        
        return null;
    }
    
    /**
     * Get optimized image tag with lazy load and WebP support
     */
    public static function getOptimizedTag(string $src, string $alt = '', string $class = '', int $width = 0, int $height = 0): string {
        $webpSrc = '';
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $localPath = __DIR__ . '/../' . ltrim($src, '/');
            if (is_file($localPath)) {
                $webp = self::convertToWebP($localPath);
                if ($webp) {
                    $webpSrc = str_replace(__DIR__ . '/../', '/', $webp);
                }
            }
        }
        
        $sizeAttr = '';
        if ($width > 0) $sizeAttr .= " width=\"$width\"";
        if ($height > 0) $sizeAttr .= " height=\"$height\"";
        
        $classAttr = $class ? " class=\"$class\"" : '';
        $altAttr = htmlspecialchars($alt);
        
        if ($webpSrc) {
            return "<picture><source srcset=\"$webpSrc\" type=\"image/webp\"><img src=\"$src\" alt=\"$altAttr\"$classAttr$sizeAttr loading=\"lazy\" decoding=\"async\"></picture>";
        }
        
        return "<img src=\"$src\" alt=\"$altAttr\"$classAttr$sizeAttr loading=\"lazy\" decoding=\"async\">";
    }
    
    /**
     * Auto-convert all images in uploads folder to WebP (cron)
     */
    public static function batchConvert(int $limit = 20): array {
        $uploadDir = __DIR__ . '/../assets/uploads';
        if (!is_dir($uploadDir)) return ['converted' => 0];
        
        $files = glob($uploadDir . '/*.{jpg,jpeg,png}', GLOB_BRACE);
        $converted = 0;
        foreach (array_slice($files, 0, $limit) as $file) {
            $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
            if (!is_file($webp)) {
                if (self::convertToWebP($file)) $converted++;
            }
        }
        return ['converted' => $converted, 'total' => count($files)];
    }
}
