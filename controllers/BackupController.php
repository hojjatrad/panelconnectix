<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ServerBackupManager.php';

class BackupController {
    
    public function index(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();
        $serverId = isset($_GET['server_id']) ? (int)$_GET['server_id'] : 0;
        $backups = ServerBackupManager::listBackups($serverId, 100);
        $servers = $pdo->query("SELECT id, name FROM server_nodes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        require __DIR__ . '/../views/backups/index.php';
    }
    
    public function create(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('backups');
        }
        $serverId = (int)($_POST['server_id'] ?? 0);
        $type = $_POST['type'] ?? 'full';
        $note = trim($_POST['note'] ?? '');
        
        $result = ServerBackupManager::createBackup($serverId, $type, false, $note);
        if ($result['success']) {
            Helpers::flash('success', "بکاپ با موفقیت ساخته شد: {$result['file_name']} ({$result['clients_count']} کلاینت، {$result['plans_count']} پلن، {$result['categories_count']} دسته)");
        } else {
            Helpers::flash('error', 'خطا در ساخت بکاپ: '.($result['error'] ?? 'نامشخص'));
        }
        Helpers::redirect('backups'.($serverId ? "?server_id=$serverId" : ''));
    }
    
    public function download(string $id = ''): void {
        Auth::requireAdmin();
        $id = (int)$id;
        $backup = ServerBackupManager::getBackup($id);
        if (!$backup || !file_exists($backup['file_path'])) {
            Helpers::flash('error', 'فایل بکاپ یافت نشد');
            Helpers::redirect('backups');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$backup['file_name'].'"');
        header('Content-Length: '.filesize($backup['file_path']));
        header('Cache-Control: no-cache');
        readfile($backup['file_path']);
        exit;
    }
    
    public function delete(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('backups');
        }
        $id = (int)$id;
        if (ServerBackupManager::deleteBackup($id)) {
            Helpers::flash('success', 'بکاپ حذف شد');
        } else {
            Helpers::flash('error', 'خطا در حذف بکاپ');
        }
        Helpers::redirect('backups');
    }
    
    public function restore(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('backups');
        }
        $id = (int)$id;
        $mode = $_POST['mode'] ?? 'merge';
        $options = [
            'restore_categories' => !empty($_POST['restore_categories']),
            'restore_plans' => !empty($_POST['restore_plans']),
            'restore_clients' => !empty($_POST['restore_clients']),
            'mode' => $mode,
        ];
        // اگر هیچکدام تیک نخورده، همه را برگردان
        if (!$options['restore_categories'] && !$options['restore_plans'] && !$options['restore_clients']) {
            $options = ['restore_categories'=>true, 'restore_plans'=>true, 'restore_clients'=>true, 'mode'=>$mode];
        }
        
        $result = ServerBackupManager::restoreBackup($id, $options);
        if ($result['success']) {
            $s = $result['stats'];
            Helpers::flash('success', "بازگردانی موفق: {$s['categories']} دسته، {$s['plans']} پلن، {$s['clients']} کلاینت. خطاها: ".count($s['errors']));
        } else {
            Helpers::flash('error', 'خطا در بازگردانی: '.($result['error'] ?? 'نامشخص'));
        }
        Helpers::redirect('backups');
    }
    
    public function preview(string $id = ''): void {
        Auth::requireAdmin();
        $id = (int)$id;
        $backup = ServerBackupManager::getBackup($id);
        $data = ServerBackupManager::loadBackupData($id);
        if (!$backup || !$data) {
            Helpers::flash('error', 'بکاپ یافت نشد');
            Helpers::redirect('backups');
        }
        require __DIR__ . '/../views/backups/preview.php';
    }
    
    public function autoBackupAll(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('backups');
        }
        $pdo = Database::getConnection();
        $servers = $pdo->query("SELECT id FROM server_nodes")->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($servers as $sid) {
            $res = ServerBackupManager::createBackup((int)$sid, 'full', true, 'بکاپ خودکار همه سرورها');
            if ($res['success']) $count++;
        }
        Helpers::flash('success', "بکاپ خودکار $count سرور انجام شد");
        Helpers::redirect('backups');
    }

    public function sendTelegram(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('backups');
        }
        $id = (int)$id;
        $res = ServerBackupManager::sendToTelegram($id);
        if ($res['success']) {
            Helpers::flash('success', 'بکاپ به تلگرام ادمین ارسال شد');
        } else {
            Helpers::flash('error', 'خطا: '.($res['error'] ?? 'نامشخص'));
        }
        Helpers::redirect('backups');
    }

    public function exportExcel(): void {
        Auth::requireAdmin();
        $serverId = (int)($_GET['server_id'] ?? 0);
        $res = ServerBackupManager::exportClientsExcel($serverId);
        if (!$res['success'] || !file_exists($res['file_path'])) {
            Helpers::flash('error', 'خطا در خروجی اکسل');
            Helpers::redirect('backups');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$res['file_name'].'"');
        readfile($res['file_path']);
        @unlink($res['file_path']);
        exit;
    }
}
