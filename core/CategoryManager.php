<?php
require_once __DIR__ . '/Database.php';

class CategoryManager {
    // Canonical mapping: canonical name => variants
    private const CANONICAL_MAP = [
        '۱ روزه' => ['1 روزه', '۱روزه', '1روزه', 'روزانه', 'یک روزه', 'یکروزه', '1D', '1d', 'daily', 'روزه 1', '1 روز', 'یک روز', 'روزانه'],
        '۳ روزه' => ['3 روزه', '۳روزه', '3روزه', 'سه روزه', 'سه‌روزه', '3D', '3d'],
        'هفتگی' => ['هفتگی', 'هفته‌ای', 'هفته ای', '7 روزه', '۷ روزه', '1 هفته', 'یک هفته', 'یک هفته‌ای', '7D', 'weekly'],
        'نیمه ماه' => ['نیمه ماه', 'نیمه‌ماه', '15 روزه', '۱۵ روزه', 'نیم ماه', 'نیم‌ماه'],
        '۱ ماهه' => ['1 ماهه', '۱ماهه', '1ماهه', 'یک ماهه', 'یکماهه', 'یک ماه', '30 روزه', '۳۰ روزه', '1M', '1m', 'یکماه', '1 ماه', '30روزه', 'یکماهه', 'یک ماهه'],
        '۲ ماهه' => ['2 ماهه', '۲ماهه', '2ماهه', 'دو ماهه', 'دوماهه', '60 روزه', '۶۰ روزه', '2M', '2m', '2 ماه'],
        '۳ ماهه' => ['3 ماهه', '۳ماهه', '3ماهه', 'سه ماهه', 'سه‌ماهه', '90 روزه', '۹۰ روزه', '3M', '3m', '3 ماه'],
        '۶ ماهه' => ['6 ماهه', '۶ماهه', '6ماهه', 'شش ماهه', 'شش‌ماهه', '180 روزه', '۱۸۰ روزه', '6M', '6m'],
        '۱۲ ماهه' => ['12 ماهه', '۱۲ماهه', '12ماهه', 'یک ساله', 'یکساله', '1 ساله', 'یکسال', 'سالانه', '365 روزه', '۳۶۵ روزه', '12M', 'yearly', 'یک ساله', 'ساله'],
    ];

    private const DURATION_TO_CANONICAL = [
        1 => '۱ روزه',
        2 => '۳ روزه',
        3 => '۳ روزه',
        7 => 'هفتگی',
        10 => 'نیمه ماه',
        15 => 'نیمه ماه',
        30 => '۱ ماهه',
        60 => '۲ ماهه',
        90 => '۳ ماهه',
        180 => '۶ ماهه',
        360 => '۱۲ ماهه',
        365 => '۱۲ ماهه',
    ];

    private const SLUG_MAP = [
        'period_1d' => '۱ روزه',
        'daily' => '۱ روزه',
        '1d' => '۱ روزه',
        'period_3d' => '۳ روزه',
        '3d' => '۳ روزه',
        'weekly' => 'هفتگی',
        '7d' => 'هفتگی',
        'period_7d' => 'هفتگی',
        'period_15d' => 'نیمه ماه',
        'period_1m' => '۱ ماهه',
        '1m' => '۱ ماهه',
        'period_2m' => '۲ ماهه',
        '2m' => '۲ ماهه',
        'period_3m' => '۳ ماهه',
        '3m' => '۳ ماهه',
        'period_6m' => '۶ ماهه',
        '6m' => '۶ ماهه',
        'period_long' => '۶ ماهه',
        'period_12m' => '۱۲ ماهه',
        '12m' => '۱۲ ماهه',
        'yearly' => '۱۲ ماهه',
        '1y' => '۱۲ ماهه',
    ];

    private static function normalizeDigits(string $str): string {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $english = ['0','1','2','3','4','5','6','7','8','9'];
        return str_replace($persian, $english, $str);
    }

    private static function buildVariantLookup(): array {
        static $lookup = null;
        if ($lookup !== null) return $lookup;
        $lookup = [];
        foreach (self::CANONICAL_MAP as $canonical => $variants) {
            $lookup[mb_strtolower(trim($canonical))] = $canonical;
            foreach ($variants as $v) {
                $lookup[mb_strtolower(trim($v))] = $canonical;
                $lookup[mb_strtolower(trim(self::normalizeDigits($v)))] = $canonical;
            }
        }
        foreach (self::SLUG_MAP as $slug => $canon) {
            $lookup[mb_strtolower($slug)] = $canon;
        }
        return $lookup;
    }

    public static function normalizeCategoryName(string $name): string {
        $lookup = self::buildVariantLookup();
        $lower = mb_strtolower(trim($name));
        $normDigits = mb_strtolower(trim(self::normalizeDigits($name)));
        return $lookup[$lower] ?? $lookup[$normDigits] ?? trim($name);
    }

    public static function canonicalFromDuration(int $days): string {
        // Exact match first
        if (isset(self::DURATION_TO_CANONICAL[$days])) {
            return self::DURATION_TO_CANONICAL[$days];
        }
        // Approximate
        if ($days <= 1) return '۱ روزه';
        if ($days <= 3) return '۳ روزه';
        if ($days <= 7) return 'هفتگی';
        if ($days <= 15) return 'نیمه ماه';
        if ($days <= 30) return '۱ ماهه';
        if ($days <= 60) return '۲ ماهه';
        if ($days <= 90) return '۳ ماهه';
        if ($days <= 180) return '۶ ماهه';
        return '۱۲ ماهه';
    }

    /**
     * Smart findOrCreateCategory — the core fix for duplicate categories
     * 1. Normalize name to canonical
     * 2. Search by exact name, slug, alias, duration
     * 3. Only create if truly not exists
     */
    public static function findOrCreateCategory(PDO $pdo, string $name, ?int $durationDays = null, string $type = 'plans'): array {
        $canonical = self::normalizeCategoryName($name);
        if ($durationDays !== null) {
            // If duration provided, canonical from duration takes priority if name is variant
            $durationCanonical = self::canonicalFromDuration($durationDays);
            // If normalized name is different from duration canonical but duration is reliable, use duration canonical
            // Only if original name was a duration-like string (contains روز or ماه or M/D)
            $lowerOrig = mb_strtolower($name);
            if (preg_match('/روز|ماه|M|D|هفته|سال/', $name) || is_numeric(trim(self::normalizeDigits($name)))) {
                $canonical = $durationCanonical;
            }
        }

        // 1. Search by exact name
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE name = ? LIMIT 1");
        $stmt->execute([$canonical]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat) {
            return $cat;
        }

        // 2. Search by normalized variants via alias table and via LIKE
        $lookup = self::buildVariantLookup();
        $variants = [];
        foreach ($lookup as $variant => $canon) {
            if ($canon === $canonical) {
                $variants[] = $variant;
            }
        }
        // Search categories where name matches any variant (case insensitive)
        if (!empty($variants)) {
            // Build query for variants
            $placeholders = implode(',', array_fill(0, count($variants), '?'));
            // Lowercase comparison
            $sql = "SELECT * FROM categories WHERE LOWER(name) IN ($placeholders) LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($variants);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat) {
                return $cat;
            }
        }

        // 3. Search by alias table
        try {
            $stmt = $pdo->prepare("SELECT c.* FROM categories c JOIN category_aliases ca ON ca.category_id = c.id WHERE ca.alias = ? LIMIT 1");
            $stmt->execute([$canonical]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat) return $cat;
            // Also search aliases matching variants
            foreach ($variants as $v) {
                $stmt = $pdo->prepare("SELECT c.* FROM categories c JOIN category_aliases ca ON ca.category_id = c.id WHERE LOWER(ca.alias) = ? LIMIT 1");
                $stmt->execute([mb_strtolower($v)]);
                $cat = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cat) return $cat;
            }
        } catch (Throwable $e) {}

        // 4. Search by slug
        $slug = self::slugify($canonical);
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cat) return $cat;

        // 5. Search by duration mapping if duration provided
        if ($durationDays !== null) {
            $durationSlugMap = [
                1 => ['period_1d', 'daily', '1d'],
                3 => ['period_3d', '3d'],
                7 => ['weekly', 'period_7d', '7d'],
                30 => ['period_1m', '1m'],
                60 => ['period_2m', '2m'],
                90 => ['period_3m', '3m'],
                180 => ['period_6m', '6m', 'period_long'],
                365 => ['period_12m', '12m', 'yearly'],
            ];
            $possibleSlugs = $durationSlugMap[$durationDays] ?? [];
            if (!empty($possibleSlugs)) {
                $ph = implode(',', array_fill(0, count($possibleSlugs), '?'));
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug IN ($ph) LIMIT 1");
                $stmt->execute($possibleSlugs);
                $cat = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cat) return $cat;
            }
        }

        // 6. Not found — create new category
        $icon = match($canonical) {
            '۱ روزه' => 'fa-calendar-day',
            '۳ روزه' => 'fa-calendar-days',
            'هفتگی' => 'fa-calendar-week',
            'نیمه ماه' => 'fa-calendar',
            '۱ ماهه' => 'fa-calendar-days',
            '۲ ماهه' => 'fa-calendar-week',
            '۳ ماهه' => 'fa-calendar-check',
            '۶ ماهه' => 'fa-calendar-plus',
            '۱۲ ماهه' => 'fa-calendar-range',
            default => 'fa-tag',
        };
        $color = match($canonical) {
            '۱ روزه' => 'rose',
            '۳ روزه' => 'amber',
            'هفتگی' => 'blue',
            'نیمه ماه' => 'cyan',
            '۱ ماهه' => 'purple',
            '۲ ماهه' => 'blue',
            '۳ ماهه' => 'amber',
            '۶ ماهه' => 'emerald',
            '۱۲ ماهه' => 'violet',
            default => 'purple',
        };
        $sortOrder = match($canonical) {
            '۱ روزه' => 5,
            '۳ روزه' => 6,
            'هفتگی' => 7,
            'نیمه ماه' => 8,
            '۱ ماهه' => 10,
            '۲ ماهه' => 11,
            '۳ ماهه' => 12,
            '۶ ماهه' => 13,
            '۱۲ ماهه' => 14,
            default => 20,
        };

        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$canonical, $slug, $type, $icon, $color, "دسته‌بندی {$canonical}", $sortOrder]);
            $id = (int)$pdo->lastInsertId();
            $cat = $pdo->query("SELECT * FROM categories WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            // Add aliases for future matching
            self::addAliases($pdo, $id, $canonical);
            return $cat;
        } catch (Throwable $e) {
            // If slug conflict, try with suffix
            $slug2 = $slug . '_' . time();
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, type, icon, badge_color, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$canonical, $slug2, $type, $icon, $color, "دسته‌بندی {$canonical}", $sortOrder]);
                $id = (int)$pdo->lastInsertId();
                return $pdo->query("SELECT * FROM categories WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e2) {
                // Last resort: return existing with same name via LIKE
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE name LIKE ? LIMIT 1");
                $stmt->execute(["%$canonical%"]);
                $cat = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cat) return $cat;
                throw $e2;
            }
        }
    }

    public static function addAliases(PDO $pdo, int $categoryId, string $canonical): void {
        $lookup = self::buildVariantLookup();
        $aliases = [];
        foreach ($lookup as $variant => $canon) {
            if ($canon === $canonical) {
                $aliases[] = $variant;
            }
        }
        // Also add canonical itself
        $aliases[] = mb_strtolower($canonical);
        $aliases = array_unique($aliases);
        try {
            foreach ($aliases as $alias) {
                if (empty($alias)) continue;
                $pdo->prepare("INSERT OR IGNORE INTO category_aliases (category_id, alias) VALUES (?, ?)")->execute([$categoryId, $alias]);
            }
        } catch (Throwable $e) {
            // MySQL uses different syntax
            try {
                foreach ($aliases as $alias) {
                    if (empty($alias)) continue;
                    $pdo->prepare("INSERT IGNORE INTO category_aliases (category_id, alias) VALUES (?, ?)")->execute([$categoryId, $alias]);
                }
            } catch (Throwable $e2) {}
        }
    }

    public static function slugify(string $name): string {
        $map = [
            '۱ روزه' => 'period_1d',
            '۳ روزه' => 'period_3d',
            'هفتگی' => 'weekly',
            'نیمه ماه' => 'period_15d',
            '۱ ماهه' => 'period_1m',
            '۲ ماهه' => 'period_2m',
            '۳ ماهه' => 'period_3m',
            '۶ ماهه' => 'period_6m',
            '۱۲ ماهه' => 'period_12m',
        ];
        if (isset($map[$name])) return $map[$name];
        // Fallback: convert to ascii slug
        $slug = mb_strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug);
        $slug = trim($slug, '_');
        if (empty($slug)) $slug = 'cat_' . time();
        return $slug;
    }

    /**
     * Merge duplicate categories — finds all categories that normalize to same canonical and merges them
     */
    public static function mergeDuplicateCategories(PDO $pdo): array {
        $allCats = $pdo->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
        $groups = []; // canonical => list
        foreach ($allCats as $cat) {
            $canon = self::normalizeCategoryName($cat['name']);
            // Also check slug mapping
            $slugLower = mb_strtolower($cat['slug']);
            if (isset(self::SLUG_MAP[$slugLower])) {
                $canon = self::SLUG_MAP[$slugLower];
            }
            $groups[$canon][] = $cat;
        }

        $merged = 0;
        $details = [];
        foreach ($groups as $canonical => $group) {
            if (count($group) <= 1) continue;
            // Sort: prefer period_* slug, then lowest id
            usort($group, function($a,$b){
                $aScore = str_starts_with($a['slug'], 'period_') ? 10 : 0;
                $bScore = str_starts_with($b['slug'], 'period_') ? 10 : 0;
                if ($a['name'] === $b['name']) {
                    return $aScore <=> $bScore;
                }
                return $bScore <=> $aScore ?: ($a['id'] <=> $b['id']);
            });
            $primary = $group[0];
            $primaryId = $primary['id'];
            // Ensure primary name is canonical
            if ($primary['name'] !== $canonical) {
                $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$canonical, $primaryId]);
            }
            $dupIds = [];
            for ($i=1; $i<count($group); $i++) {
                $dup = $group[$i];
                $dupId = $dup['id'];
                if ($dupId == $primaryId) continue;
                $dupIds[] = $dupId;
                try {
                    $pdo->prepare("UPDATE plans SET category_id = ? WHERE category_id = ?")->execute([$primaryId, $dupId]);
                    $pdo->prepare("UPDATE server_nodes SET category_id = ? WHERE category_id = ?")->execute([$primaryId, $dupId]);
                    $pdo->prepare("UPDATE categories SET parent_id = ? WHERE parent_id = ?")->execute([$primaryId, $dupId]);
                    // Move aliases
                    $pdo->prepare("UPDATE category_aliases SET category_id = ? WHERE category_id = ?")->execute([$primaryId, $dupId]);
                    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$dupId]);
                    $merged++;
                } catch (Throwable $e) {}
            }
            if (!empty($dupIds)) {
                $details[] = "Merged " . implode(',', $dupIds) . " into {$primaryId} ({$canonical})";
            }
        }
        return ['merged' => $merged, 'details' => $details];
    }

    /**
     * Normalize plan data from API — uses real API fields, not regex on title
     */
    public static function normalizePlan(array $vipPlan, array $vipGroups = []): array {
        // Try to get real fields from API if available
        $title = $vipPlan['title'] ?? $vipPlan['name'] ?? '';
        $trafficGb = null;
        $durationDays = null;
        $price = null;
        $groupId = $vipPlan['group_id'] ?? $vipPlan['groupId'] ?? null;
        $groupName = null;

        // Real API fields (connectix.vip API returns these)
        if (isset($vipPlan['traffic_gb'])) $trafficGb = (float)$vipPlan['traffic_gb'];
        if (isset($vipPlan['traffic'])) $trafficGb = (float)$vipPlan['traffic'] / (1024*1024*1024);
        if (isset($vipPlan['data_limit'])) $trafficGb = (float)$vipPlan['data_limit'] / (1024*1024*1024);
        if (isset($vipPlan['volume'])) $trafficGb = (float)$vipPlan['volume'];
        
        if (isset($vipPlan['duration_days'])) $durationDays = (int)$vipPlan['duration_days'];
        if (isset($vipPlan['duration'])) $durationDays = (int)$vipPlan['duration'];
        if (isset($vipPlan['expire_days'])) $durationDays = (int)$vipPlan['expire_days'];
        if (isset($vipPlan['time_days'])) $durationDays = (int)$vipPlan['time_days'];

        if (isset($vipPlan['price'])) $price = (int)$vipPlan['price'];
        if (isset($vipPlan['retail_price'])) $price = (int)$vipPlan['retail_price'];
        if (isset($vipPlan['cost'])) $price = (int)$vipPlan['cost'];

        // Group detection from real group_id
        if (!empty($groupId) && !empty($vipGroups)) {
            foreach ($vipGroups as $g) {
                if (($g['id'] ?? '') === $groupId) {
                    $groupName = $g['name'] ?? $g['title'] ?? null;
                    break;
                }
            }
        }
        if (empty($groupName) && isset($vipPlan['group_name'])) $groupName = $vipPlan['group_name'];
        if (empty($groupName) && isset($vipPlan['group'])) $groupName = $vipPlan['group'];

        // Fallback to regex parsing if real fields not available
        if ($trafficGb === null) {
            if (preg_match('/([\d\.]+)\s*GB/i', $title, $m)) {
                $trafficGb = (float)$m[1];
            } elseif (stripos($title, 'Unlimited') !== false) {
                $trafficGb = 1000;
            } elseif (preg_match('/([\d\.]+)\s*MB/i', $title, $m)) {
                $trafficGb = round((float)$m[1] / 1024, 4);
            } else {
                $trafficGb = 10;
            }
        }

        if ($durationDays === null) {
            $durationDays = 30;
            // Try multiple patterns
            if (preg_match('/(\d+)\s*M/i', $title, $m)) {
                $durationDays = (int)$m[1] * 30;
            }
            if (preg_match('/(\d+)\s*D/i', $title, $m)) {
                $d = (int)$m[1];
                // If title has +10D, add it
                if (preg_match('/\+\s*(\d+)\s*D/i', $title, $m2)) {
                    $d += (int)$m2[1];
                }
                // If M already matched, D might be bonus
                if ($durationDays !== 30 || !preg_match('/\d+\s*M/i', $title)) {
                    $durationDays = $d;
                } else {
                    if (preg_match('/\+\s*(\d+)\s*D/i', $title, $m2)) {
                        $durationDays += (int)$m2[1];
                    }
                }
            }
        }

        // Group detection fallback from title if not from API
        $serverGroup = 'default';
        if (empty($groupName)) {
            if (stripos($title, 'Economic') !== false || stripos($title, 'اقتصادی') !== false) {
                $groupName = 'Economic';
                $serverGroup = 'economic';
            } elseif (stripos($title, 'Iran Access') !== false || stripos($title, 'ایران') !== false) {
                $groupName = 'Iran Access';
                $serverGroup = 'iran_access';
            } elseif (stripos($title, 'Business') !== false || stripos($title, 'بیزنس') !== false) {
                $groupName = 'Business Class';
                $serverGroup = 'business';
            } else {
                $groupName = 'default';
                $serverGroup = 'default';
            }
        } else {
            // Map groupName to serverGroup
            $lowerGroup = mb_strtolower($groupName);
            if (str_contains($lowerGroup, 'economic') || str_contains($lowerGroup, 'اقتصادی')) {
                $serverGroup = 'economic';
            } elseif (str_contains($lowerGroup, 'iran')) {
                $serverGroup = 'iran_access';
            } elseif (str_contains($lowerGroup, 'business')) {
                $serverGroup = 'business';
            } else {
                $serverGroup = 'default';
            }
        }

        return [
            'title' => $title,
            'traffic_gb' => $trafficGb,
            'duration_days' => $durationDays,
            'price' => $price,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'server_group' => $serverGroup,
        ];
    }
}
