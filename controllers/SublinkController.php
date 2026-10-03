<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/SublinkRotator.php';

class SublinkController {
    
    public function index(): void {
        Auth::requireAdmin();
        $domains = SublinkRotator::getActiveDomains();
        $primary = SublinkRotator::getPrimaryDomain();
        $best = SublinkRotator::getBestDomain();
        
        require __DIR__ . '/../views/settings/sublink_domains.php';
    }
    
    public function add(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/sublink-domains');
        }
        
        $domain = trim($_POST['domain'] ?? '');
        $isPrimary = !empty($_POST['is_primary']);
        
        $result = SublinkRotator::addDomain($domain, $isPrimary);
        if ($result['success']) {
            Helpers::flash('success', "دامنه $domain اضافه شد");
        } else {
            Helpers::flash('error', $result['error'] ?? 'خطا');
        }
        Helpers::redirect('settings/sublink-domains');
    }
    
    public function delete(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/sublink-domains');
        }
        $id = (int)$id;
        if (SublinkRotator::deleteDomain($id)) {
            Helpers::flash('success', 'دامنه حذف شد');
        } else {
            Helpers::flash('error', 'خطا در حذف');
        }
        Helpers::redirect('settings/sublink-domains');
    }
    
    public function toggle(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/sublink-domains');
        }
        $id = (int)$id;
        SublinkRotator::toggleDomain($id);
        Helpers::flash('success', 'وضعیت تغییر کرد');
        Helpers::redirect('settings/sublink-domains');
    }
    
    public function setPrimary(string $id = ''): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/sublink-domains');
        }
        $id = (int)$id;
        SublinkRotator::setPrimary($id);
        Helpers::flash('success', 'دامنه اصلی تنظیم شد');
        Helpers::redirect('settings/sublink-domains');
    }
    
    public function check(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن نامعتبر');
            Helpers::redirect('settings/sublink-domains');
        }
        $results = SublinkRotator::checkAllDomains();
        $online = count(array_filter($results, fn($r) => $r['status'] === 'online'));
        Helpers::flash('success', "بررسی شد: $online آنلاین");
        Helpers::redirect('settings/sublink-domains');
    }
}
