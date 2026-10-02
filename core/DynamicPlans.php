<?php
/**
 * F4: Dynamic Plans Builder - Admin can create plans without coding
 */

class DynamicPlans {
    public static function createPlan(array $data): array {
        try {
            $pdo = Database::getConnection();
            
            $required = ['name', 'volume_gb', 'days', 'price'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => "فیلد $field الزامی است"];
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO plans (name, volume_gb, days, price, is_active, is_featured, description, created_at)
                VALUES (?, ?, ?, ?, 1, ?, ?, NOW())
            ");
            $stmt->execute([
                $data['name'],
                (int)$data['volume_gb'],
                (int)$data['days'],
                (int)$data['price'],
                !empty($data['is_featured']) ? 1 : 0,
                $data['description'] ?? ''
            ]);
            
            $planId = $pdo->lastInsertId();
            
            // Clear cache
            if (class_exists('Cache')) {
                Cache::delete('plans_active_v2');
            }
            if (class_exists('RedisCache')) {
                RedisCache::delete('plans_active_v2');
            }
            
            return ['success' => true, 'plan_id' => $planId, 'message' => 'پلن با موفقیت ایجاد شد'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public static function getPlanTemplates(): array {
        return [
            ['name' => 'اقتصادی 1 ماهه 30 گیگ', 'volume_gb' => 30, 'days' => 30, 'price' => 50000],
            ['name' => 'اقتصادی 1 ماهه 50 گیگ', 'volume_gb' => 50, 'days' => 30, 'price' => 70000],
            ['name' => 'حرفه‌ای 1 ماهه 100 گیگ', 'volume_gb' => 100, 'days' => 30, 'price' => 120000],
            ['name' => 'ویژه 3 ماهه 150 گیگ', 'volume_gb' => 150, 'days' => 90, 'price' => 300000],
            ['name' => 'نامحدود 1 ماهه', 'volume_gb' => 1000, 'days' => 30, 'price' => 200000],
        ];
    }
}
