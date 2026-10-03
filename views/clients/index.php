<?php
require __DIR__ . '/../layout/header.php';

function fmtBytes($b){ $b=(int)$b; if($b<=0) return '0'; $u=['B','KB','MB','GB','TB']; $i=0; while($b>=1024 && $i<4){ $b/=1024; $i++; } return round($b,2).' '.$u[$i]; }
function gb($bytes){ return round(((int)$bytes)/1073741824,2); }
function initials($name,$user){
    $name=trim((string)$name);
    if($name!==''){
        $parts=preg_split('/\s+/u',$name);
        $ini='';
        foreach($parts as $p){
            if($p!=='') $ini.=mb_substr($p,0,1,'UTF-8');
            if(mb_strlen($ini,'UTF-8')>=2) break;
        }
        return mb_strtoupper($ini,'UTF-8');
    }
    return strtoupper(substr($user,0,2));
}
?>

<!-- Header -->
<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600/20 to-indigo-600/20 border border-purple-700/30 flex items-center justify-center text-purple-300">
            <i class="fa-solid fa-users text-lg"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span>مدیریت کلاینت‌ها و مشترکین</span>
                <span class="px-2.5 py-0.5 rounded-full bg-purple-900/30 border border-purple-700/40 text-[11px] text-purple-300 font-mono"><?= count($clients) ?> کاربر</span>
                <span class="px-2 py-0.5 rounded-full bg-cyan-900/30 border border-cyan-700/40 text-[10px] text-cyan-300">حرفه‌ای - شبیه VIP</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">نمایش: <b class="text-white">نام مشتری</b> + یوزرنیم + پسورد قابل کپی + پلن/سرور + مصرف + انقضا — دقیقا مثل پنل VIP Connectix</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= Helpers::url('clients/restore-traffic') ?>" onclick="return confirm('بازیابی ترافیک واقعی؟');" class="px-3 py-2 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 border border-cyan-500/30 text-xs font-bold rounded-xl transition"><i class="fa-solid fa-chart-pie ml-1"></i>بازیابی مصرف</a>
        <a href="<?= Helpers::url('servers/sync') ?>" class="px-3 py-2 bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 border border-purple-500/30 text-xs font-bold rounded-xl transition"><i class="fa-solid fa-cloud-arrow-down ml-1"></i>بازخوانی</a>
        <button type="button" onclick="openOptimizerModal()" class="px-3 py-2 bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 text-xs font-bold rounded-xl transition"><i class="fa-solid fa-broom ml-1"></i>پاکسازی <?php $pendingClean=($optimizerStats['unused']??0)+($optimizerStats['expired_7d']??0); if($pendingClean>0): ?><span class="bg-rose-600 text-white px-1.5 rounded-full text-[10px]"><?= $pendingClean ?></span><?php endif; ?></button>
        <button type="button" onclick="openTestModal()" class="px-3 py-2 bg-cyan-600/20 hover:bg-cyan-600/30 text-cyan-300 border border-cyan-500/30 text-xs font-bold rounded-xl transition"><i class="fa-solid fa-wand-magic-sparkles ml-1"></i>تست سریع</button>
        <a href="<?= Helpers::url('clients/bulk') ?>" class="px-3 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow"><i class="fa-solid fa-layer-group ml-1"></i>گروهی</a>
        <a href="<?= Helpers::url('clients/create') ?>" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-lg"><i class="fa-solid fa-plus ml-1"></i>کاربر جدید</a>
    </div>
</div>

<!-- Quick Filters -->
<?php $currentFilter = $_GET['filter'] ?? $_GET['quick_filter'] ?? ''; ?>
<div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs no-scrollbar">
    <span class="text-slate-400 font-semibold text-[11px] whitespace-nowrap"><i class="fa-solid fa-bolt text-amber-400 ml-1"></i>فیلتر سریع:</span>
    <a href="<?= Helpers::url('clients') ?>" class="px-3 py-1.5 rounded-lg border whitespace-nowrap <?= empty($currentFilter)?'bg-purple-600 text-white border-purple-500 font-bold':'bg-slate-900/60 text-slate-300 border-slate-800 hover:bg-slate-800' ?>">همه سرویس‌ها</a>
    <a href="<?= Helpers::url('clients') ?>?filter=unused" class="px-3 py-1.5 rounded-lg border whitespace-nowrap <?= $currentFilter==='unused'?'bg-amber-600 text-white border-amber-500 font-bold':'bg-slate-900/60 text-amber-300/80 border-slate-800' ?>">بدون مصرف <span class="bg-amber-500/20 px-1 rounded"><?= $optimizerStats['unused'] ?? 0 ?></span></a>
    <a href="<?= Helpers::url('clients') ?>?filter=expired_7d" class="px-3 py-1.5 rounded-lg border whitespace-nowrap <?= $currentFilter==='expired_7d'?'bg-rose-600 text-white border-rose-500':'bg-slate-900/60 text-rose-300/80 border-slate-800' ?>">منقضی >7 روز <span class="bg-rose-500/20 px-1 rounded"><?= $optimizerStats['expired_7d'] ?? 0 ?></span></a>
    <a href="<?= Helpers::url('clients') ?>?filter=expired_all" class="px-3 py-1.5 rounded-lg border whitespace-nowrap <?= $currentFilter==='expired_all'?'bg-red-700 text-white':'bg-slate-900/60 text-red-300/80 border-slate-800' ?>">کل منقضی‌ها <span class="bg-red-500/20 px-1 rounded"><?= $optimizerStats['expired_all'] ?? 0 ?></span></a>
    <a href="<?= Helpers::url('clients') ?>?filter=trials" class="px-3 py-1.5 rounded-lg border whitespace-nowrap <?= $currentFilter==='trials'?'bg-cyan-700 text-white':'bg-slate-900/60 text-cyan-300 border-slate-800' ?>">تست‌ها <span class="bg-cyan-500/20 px-1 rounded"><?= $optimizerStats['expired_trials'] ?? 0 ?></span></a>
</div>

<!-- Search & Advanced Filters -->
<form method="GET" action="<?= Helpers::url('clients') ?>" class="bg-slate-900/50 p-4 rounded-xl border border-slate-800/80 text-xs space-y-3">
    <?php if(!empty($currentFilter)): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($currentFilter) ?>"><?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="lg:col-span-2 relative">
            <i class="fa-solid fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="نام مشتری، یوزرنیم، پسورد، UUID، یادداشت..." class="w-full bg-slate-800 border border-slate-700 rounded-lg pr-9 pl-3 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-purple-500">
        </div>
        <select name="status" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2.5 text-white"><option value="">همه وضعیت‌ها</option><option value="active" <?= ($_GET['status']??'')==='active'?'selected':'' ?>>فعال</option><option value="expired" <?= ($_GET['status']??'')==='expired'?'selected':'' ?>>منقضی</option><option value="disabled" <?= ($_GET['status']??'')==='disabled'?'selected':'' ?>>غیرفعال</option></select>
        <select name="group" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2.5 text-white"><option value="">همه گروه‌ها</option><option value="default">عادی</option><option value="economic">اقتصادی</option><option value="vip">VIP</option></select>
        <select name="plan_id" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2.5 text-white"><option value="">همه پلن‌ها</option><?php foreach($plans as $p): ?><option value="<?= $p['id'] ?>" <?= (int)($_GET['plan_id']??0)===$p['id']?'selected':'' ?>><?= htmlspecialchars($p['title']) ?> (<?= $p['traffic_gb'] ?>GB)</option><?php endforeach; ?></select>
        <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-bold flex items-center justify-center gap-1"><i class="fa-solid fa-filter"></i>اعمال فیلتر</button>
    </div>
</form>

<!-- Bulk Toolbar -->
<form action="<?= Helpers::url('clients/bulk') ?>" method="POST" id="bulkForm">
<?= Helpers::csrfField() ?>
<div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-900/60 p-3 rounded-xl border border-slate-800 text-xs">
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-slate-400 font-semibold">عملیات گروهی:</span>
        <select name="bulk_action" required class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white"><option value="">-- انتخاب --</option><option value="extend_30_days">+30 روز</option><option value="add_10_gb">+10GB</option><option value="disable">غیرفعال</option><option value="enable">فعال</option><option value="delete">حذف</option></select>
        <button type="submit" onclick="var act=this.form.bulk_action.value; if(act==='delete'){return confirm('⚠️ حذف گروهی - اخطار حرفه‌ای:\n\nقبل از حذف، بکاپ خودکار از کلاینت‌های انتخاب شده گرفته می‌شود\n📦 بکاپ شامل ساب‌لینک دقیق + ترافیک + تاریخ انقضا\nقابل بازگردانی از بخش بکاپ‌ها\n\nآیا ادامه می‌دهید؟');} return confirm('اعمال روی انتخاب‌شده‌ها؟')" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-lg shadow">اعمال</button>
    </div>
    <div class="flex items-center gap-3">
        <span class="text-slate-400">نمایش <?= count($clients) ?> کاربر</span>
        <a href="<?= Helpers::url('clients/export') ?>?<?= http_build_query($_GET) ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700"><i class="fa-solid fa-file-excel ml-1"></i>اکسل</a>
    </div>
</div>

<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
<div class="overflow-x-auto">
<table class="w-full text-xs">
<thead class="bg-slate-800/80 text-slate-400 border-b border-slate-700/50 text-[11px] uppercase tracking-wider">
<tr>
<th class="p-3 text-center w-10"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0"></th>
<th class="p-3.5 font-bold text-right">مشتری / احراز هویت</th>
<th class="p-3.5 font-bold text-right">پلن / سرور / گروه</th>
<th class="p-3.5 font-bold text-right">مصرف / ترافیک</th>
<th class="p-3.5 font-bold text-right">انقضا / باقی‌مانده</th>
<th class="p-3.5 font-bold text-right">وضعیت / رزرو</th>
<th class="p-3.5 font-bold text-center">عملیات</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-800/50" id="clients_body">
<?php if(empty($clients)): ?>
<tr><td colspan="7" class="py-16 text-center"><i class="fa-solid fa-inbox text-4xl text-slate-700 mb-3 block"></i><span class="text-slate-500">هیچ کاربری یافت نشد</span></td></tr>
<?php else: foreach($clients as $c):
$pct = $c['traffic_limit_bytes']>0 ? round(($c['traffic_used_bytes']/$c['traffic_limit_bytes'])*100,1) : 0;
$subUrlRaw = !empty($c['node_sublink']) ? $c['node_sublink'] : Helpers::subUrl($c['sub_token']);
$subUrl = Helpers::fixSublinkDomain($subUrlRaw);
$daysRemText = Helpers::daysRemaining($c['expire_at']);
$isExpired = str_contains($daysRemText, 'منقضی') || $c['status']==='expired';
$ini = initials($c['customer_name'] ?? '', $c['username']);
$barColor = $pct>=90?'bg-rose-500':($pct>=70?'bg-amber-500':'bg-emerald-500');
if($isExpired) $barColor='bg-slate-600';
$statusCfg = match($c['status']){
 'active'=>['label'=>'فعال','cls'=>'bg-emerald-500/15 text-emerald-300 border-emerald-500/30','dot'=>'bg-emerald-400'],
 'waiting_connect'=>['label'=>'در انتظار اتصال','cls'=>'bg-indigo-500/15 text-indigo-300 border-indigo-500/30','dot'=>'bg-indigo-400'],
 'expired'=>['label'=>'منقضی','cls'=>'bg-amber-500/15 text-amber-300 border-amber-500/30','dot'=>'bg-amber-400'],
 'disabled'=>['label'=>'غیرفعال','cls'=>'bg-rose-500/15 text-rose-300 border-rose-500/30','dot'=>'bg-rose-400'],
 'limited'=>['label'=>'حجم تمام','cls'=>'bg-rose-500/15 text-rose-300 border-rose-500/30','dot'=>'bg-rose-500'],
 default=>['label'=>htmlspecialchars($c['status']),'cls'=>'bg-slate-500/15 text-slate-300 border-slate-500/30','dot'=>'bg-slate-400'],
};
$remBadge='';
if(preg_match('/(\d+)\s*روز/', $daysRemText, $m)){
 $d=(int)$m[1];
 if($d<=1) $remBadge='<span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px] font-bold">امروز تمام می‌شود</span>';
 elseif($d<=3) $remBadge='<span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px] font-bold">'.$d.' روز مانده</span>';
 else $remBadge='<span class="px-2 py-0.5 rounded-full bg-slate-700 text-slate-300 border border-slate-600 text-[10px]">'.$d.' روز</span>';
} elseif($isExpired){
 $remBadge='<span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px]">منقضی</span>';
}
?>
<tr class="hover:bg-slate-800/40 transition group" data-search="<?= htmlspecialchars(strtolower(($c['customer_name']??'').' '.$c['username'].' '.$c['password'].' '.($c['plan_title']??'').' '.($c['server_name']??'').' '.$c['status'])) ?>" data-status="<?= $c['status'] ?>">
<td class="p-3 text-center"><input type="checkbox" name="selected_ids[]" value="<?= $c['id'] ?>" class="client-check rounded bg-slate-800 border-slate-700 text-purple-600 focus:ring-0"></td>
<!-- Identity: نام مشتری + یوزر + پسورد -->
<td class="p-3.5">
<div class="flex items-start gap-3">
<div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500/20 to-indigo-600/20 border border-purple-700/30 flex items-center justify-center text-[12px] font-bold text-purple-300 shrink-0 shadow-inner"><?= htmlspecialchars($ini) ?></div>
<div class="min-w-0 flex-1">
<?php if(!empty($c['customer_name'])): ?>
<div class="text-[14px] font-bold text-white leading-tight truncate max-w-[200px]" title="<?= htmlspecialchars($c['customer_name']) ?>"><?= htmlspecialchars($c['customer_name']) ?></div>
<div class="flex items-center gap-1.5 mt-1 flex-wrap">
<span class="inline-flex items-center gap-1 font-mono text-[11px] text-cyan-300 bg-cyan-950/40 border border-cyan-800/40 rounded-lg px-2 py-1" dir="ltr">
<i class="fa-solid fa-user text-[9px]"></i><?= htmlspecialchars($c['username']) ?>
<button type="button" data-copy="<?= htmlspecialchars($c['username'],ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'),this)" class="hover:text-cyan-100 ml-1"><i class="fa-solid fa-copy text-[9px]"></i></button>
</span>
<span class="inline-flex items-center gap-1 font-mono text-[11px] text-amber-300 bg-amber-950/30 border border-amber-800/30 rounded-lg px-2 py-1" dir="ltr">
<i class="fa-solid fa-key text-[9px]"></i>
<span id="pwd_<?= $c['id'] ?>" class="tracking-wider">••••••</span>
<span data-real="<?= htmlspecialchars($c['password'],ENT_QUOTES) ?>" data-id="<?= $c['id'] ?>" onclick="togglePwd(this)" class="cursor-pointer hover:text-amber-100 bg-amber-800/20 rounded px-1"><i class="fa-solid fa-eye text-[10px]"></i></span>
<button type="button" data-copy="<?= htmlspecialchars($c['password'],ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'),this)" class="hover:text-amber-100"><i class="fa-solid fa-copy text-[9px]"></i></button>
</span>
</div>
<?php else: ?>
<div class="text-[13px] font-bold text-white font-mono flex items-center gap-2" dir="ltr">
<span><?= htmlspecialchars($c['username']) ?></span>
<span class="w-2 h-2 bg-amber-400 rounded-full animate-pulse" title="بدون نام مشتری"></span>
</div>
<div class="flex items-center gap-1.5 mt-1">
<span class="font-mono text-[11px] text-amber-300 bg-amber-950/30 border border-amber-800/30 rounded-lg px-2 py-1" dir="ltr"><i class="fa-solid fa-key text-[9px] ml-1"></i><?= htmlspecialchars($c['password']) ?></span>
<span class="text-[10px] text-amber-400/60">بدون نام — ویرایش کنید</span>
</div>
<?php endif; ?>
<div class="flex items-center gap-1.5 mt-1.5">
<span class="text-[10px] font-mono text-slate-500 bg-slate-800/60 border border-slate-700/30 rounded px-1.5 py-0.5" dir="ltr" title="<?= htmlspecialchars($c['uuid']) ?>"><?= htmlspecialchars(substr($c['uuid'],0,8)) ?>...</span>
<?php if(!empty($c['node_sync'])): ?><span class="text-[9px] px-1.5 py-0.5 rounded bg-emerald-950/60 text-emerald-300 border border-emerald-800/40"><i class="fa-solid fa-server ml-0.5"></i>مستقیم سرور</span><?php endif; ?>
<?php if(Auth::isAdmin() && !empty($c['reseller_username'])): ?><span class="text-[9px] px-1.5 py-0.5 rounded bg-purple-950/60 text-purple-300 border border-purple-800/40"><?= htmlspecialchars($c['reseller_username']) ?></span><?php endif; ?>
</div>
</div>
</div>
</td>
<!-- Plan / Server / Group -->
<td class="p-3.5">
<div class="space-y-1.5">
<div class="text-[11px] font-bold text-slate-100 bg-slate-800/80 border border-slate-700/60 rounded-lg px-2.5 py-1.5 inline-flex items-center gap-1.5 max-w-[180px] truncate" title="<?= htmlspecialchars($c['plan_title'] ?? 'بدون پلن') ?>">
<i class="fa-solid fa-box text-purple-400 text-[10px]"></i><span><?= htmlspecialchars($c['plan_title'] ?? 'بدون پلن') ?></span>
</div>
<div class="flex items-center gap-1 text-[11px] text-slate-400"><i class="fa-solid fa-server text-[10px] text-cyan-400"></i><span class="truncate max-w-[140px]"><?= htmlspecialchars($c['server_name'] ?? 'سرور ابری') ?></span></div>
<div class="flex items-center gap-1 flex-wrap">
<span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300"><i class="fa-solid fa-users text-[9px]"></i><?= !empty($c['ip_limit']) && (int)$c['ip_limit']>0 ? (int)$c['ip_limit'].' دستگاه' : 'نامحدود' ?></span>
<?php if(!empty($c['group_name']) || !empty($_GET['group'])): ?><span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-900/20 text-amber-300 border border-amber-700/30"><?= htmlspecialchars($c['group_name'] ?? ($_GET['group'] ?? 'default')) ?></span><?php endif; ?>
</div>
</div>
</td>
<!-- Usage -->
<td class="p-3.5 min-w-[150px]" dir="ltr">
<div class="flex items-baseline gap-1.5"><span class="text-slate-100 font-mono font-bold text-[13px]"><?= gb($c['traffic_used_bytes']) ?></span><span class="text-slate-500 text-[11px]">/ <?= $c['traffic_limit_bytes']>0 ? gb($c['traffic_limit_bytes']).' GB' : '∞' ?></span></div>
<div class="w-full max-w-[140px] h-2 bg-slate-800 rounded-full mt-2 overflow-hidden border border-slate-700/30"><div class="h-full rounded-full <?= $barColor ?> transition-all duration-500" style="width:<?= min(100,$pct) ?>%"></div></div>
<div class="flex items-center justify-between max-w-[140px] mt-1"><span class="text-[10px] text-slate-400"><?= $pct ?>%</span><span class="text-[10px] text-slate-500 font-mono"><?= fmtBytes($c['traffic_used_bytes']) ?> / <?= fmtBytes($c['traffic_limit_bytes']) ?></span></div>
</td>
<!-- Expire -->
<td class="p-3.5">
<div class="text-[12px] font-bold text-white"><?= htmlspecialchars($daysRemText) ?></div>
<div class="font-mono text-slate-400 text-[10px] mt-1" dir="ltr"><?= $c['expire_at'] ?: '∞ نامحدود' ?></div>
<div class="mt-1.5"><?= $remBadge ?></div>
</td>
<!-- Status -->
<td class="p-3.5">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-bold <?= $statusCfg['cls'] ?>"><span class="w-1.5 h-1.5 rounded-full <?= $statusCfg['dot'] ?> <?= $statusCfg['dot']==='bg-emerald-400'?'animate-pulse':'' ?>"></span><?= $statusCfg['label'] ?></span>
<?php if(!empty($c['reserved_id'])): ?><div class="mt-2"><span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-[10px] font-bold"><i class="fa-solid fa-sparkles text-[9px]"></i>رزرو: <?= $c['reserved_gb'] ?>GB</span></div>
<?php else: ?><button type="button" onclick="openReserveModal(<?= $c['id'] ?>,'<?= htmlspecialchars($c['username']) ?>')" class="mt-2 text-[10px] text-slate-500 hover:text-cyan-400 flex items-center gap-1 transition"><i class="fa-solid fa-plus text-[8px]"></i>رزرو پلن</button><?php endif; ?>
</td>
<!-- Actions - with Copy Delivery button -->
<td class="p-3.5 text-center">
<div class="flex items-center justify-center gap-1 flex-wrap max-w-[180px] mx-auto">
<!-- Copy Delivery: username + password + sublink together -->
<button type="button" 
        data-username="<?= htmlspecialchars($c['username'],ENT_QUOTES) ?>"
        data-password="<?= htmlspecialchars($c['password'],ENT_QUOTES) ?>"
        data-sub="<?= htmlspecialchars($subUrl,ENT_QUOTES) ?>"
        data-customer="<?= htmlspecialchars($c['customer_name'] ?? '',ENT_QUOTES) ?>"
        data-plan="<?= htmlspecialchars($c['plan_title'] ?? '',ENT_QUOTES) ?>"
        onclick="copyDelivery(this)"
        class="w-8 h-8 flex items-center justify-center bg-gradient-to-br from-emerald-600/30 to-teal-600/30 hover:from-emerald-600/50 hover:to-teal-600/50 text-emerald-300 hover:text-emerald-200 rounded-xl border border-emerald-700/40 transition group/btn shadow-sm" title="کپی یوزر + پسورد + ساب لینک برای ارسال به مشتری">
    <i class="fa-solid fa-share-nodes text-[12px] group-hover/btn:scale-110 transition"></i>
</button>
<button type="button" onclick="openInspectModal(<?= $c['id'] ?>,'<?= htmlspecialchars($c['username']) ?>','<?= $subUrl ?>')" class="w-8 h-8 flex items-center justify-center bg-slate-800 hover:bg-purple-900/40 text-purple-300 hover:text-purple-200 rounded-xl border border-slate-700 hover:border-purple-700/40 transition group/btn" title="QR و ساب‌لینک"><i class="fa-solid fa-qrcode text-[12px] group-hover/btn:scale-110 transition"></i></button>
<button type="button" data-copy="<?= htmlspecialchars($subUrl,ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'),this)" class="w-8 h-8 flex items-center justify-center bg-slate-800 hover:bg-cyan-900/40 text-cyan-300 hover:text-cyan-200 rounded-xl border border-slate-700 hover:border-cyan-700/40 transition group/btn" title="کپی ساب"><i class="fa-solid fa-link text-[12px] group-hover/btn:scale-110 transition"></i></button>
                        <a href="<?= Helpers::url('clients/'.$c['id'].'/usage') ?>" class="w-8 h-8 flex items-center justify-center bg-slate-800 hover:bg-violet-900/40 text-violet-300 hover:text-violet-200 rounded-xl border border-slate-700 hover:border-violet-700/40 transition group/btn" title="تاریخچه مصرف ULTRA"><i class="fa-solid fa-chart-area text-[12px] group-hover/btn:scale-110 transition"></i></a>
<button type="button" onclick='openEditClientModal(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' class="w-8 h-8 flex items-center justify-center bg-slate-800 hover:bg-amber-900/40 text-amber-300 hover:text-amber-200 rounded-xl border border-slate-700 hover:border-amber-700/40 transition group/btn" title="ویرایش نام/یوزر/پسورد"><i class="fa-solid fa-pen text-[11px] group-hover/btn:scale-110 transition"></i></button>
<button type="button" onclick="openRenewModal(<?= $c['id'] ?>,'<?= htmlspecialchars($c['username']) ?>')" class="w-8 h-8 flex items-center justify-center bg-slate-800 hover:bg-emerald-900/40 text-emerald-400 hover:text-emerald-300 rounded-xl border border-slate-700 hover:border-emerald-700/40 transition group/btn" title="تمدید"><i class="fa-solid fa-rotate text-[11px] group-hover/btn:scale-110 transition"></i></button>
<button type="button" onclick="if(confirm('حذف <?= htmlspecialchars($c['username']) ?>؟')){document.getElementById('deleteIdInput').value=<?= $c['id'] ?>;document.getElementById('deleteForm').submit();}" class="w-8 h-8 flex items-center justify-center bg-slate-800 hover:bg-rose-900/60 text-rose-400 hover:text-rose-200 rounded-xl border border-slate-700 hover:border-rose-700/40 transition group/btn" title="حذف"><i class="fa-solid fa-trash-can text-[11px] group-hover/btn:scale-110 transition"></i></button>
</div>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</form>

<form id="deleteForm" action="<?= Helpers::url('clients/delete') ?>" method="POST" class="hidden"><?= Helpers::csrfField() ?><input type="hidden" name="client_id" id="deleteIdInput"></form>

<!-- Modals: Keep original modals (inspect, renew, reserve, edit, test, optimizer) -->
<?php
// Load modals from separate file if exists, else inline minimal
$modalsPath = __DIR__ . '/_modals.php';
if (file_exists($modalsPath)) {
    include $modalsPath;
} else {
    // Inline essential modals (inspect, renew, edit) - full version from original
    // For brevity, include the rest via original backup
    $origBackup = glob(__DIR__ . '/index.php.bak.*');
    if (!empty($origBackup)) {
        $origContent = file_get_contents(end($origBackup));
        // Extract modals section after </form> - we will just include a simplified version
    }
}
?>

<!-- Minimal Modals for new design -->
<div id="inspectModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeInspectModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center"><i class="fa-solid fa-satellite-dish"></i></div>
            <div><h3 id="inspectUsername" class="text-base font-bold text-white font-mono">user</h3><div class="text-[11px] text-slate-400"><span id="inspectTraffic">--</span> • <span id="inspectDays" class="text-cyan-400">--</span></div></div>
        </div>
        <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3 grid grid-cols-2 gap-3 text-xs mb-4">
            <div><span class="text-slate-400 block text-[10px]">یوزرنیم:</span><span id="inspectUserField" class="font-mono font-bold text-white">--</span></div>
            <div><span class="text-slate-400 block text-[10px]">پسورد:</span><span id="inspectPassField" class="font-mono font-bold text-purple-300">--</span></div>
        </div>
        <div class="bg-white p-3 rounded-2xl text-center w-fit mx-auto mb-4"><img id="inspectQrImage" src="" alt="QR" class="w-44 h-44 mx-auto"></div>
        <div class="flex gap-2"><input type="text" id="inspectSubUrl" readonly class="flex-1 bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-slate-300 font-mono text-center"><button onclick="copyToClipboard(document.getElementById('inspectSubUrl').value,this)" class="px-4 py-2.5 bg-purple-600 text-white rounded-xl text-xs font-bold">کپی</button></div>
    </div>
</div>

<div id="renewModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl relative">
        <button onclick="closeRenewModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="text-base font-bold text-white mb-4">تمدید <span id="renewUsername" class="font-mono text-emerald-400"></span></h3>
        <form action="<?= Helpers::url('clients/renew') ?>" method="POST" class="space-y-3 text-xs"><?= Helpers::csrfField() ?><input type="hidden" name="client_id" id="renewClientId"><select name="plan_id" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><?php foreach($plans as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?> (<?= $p['traffic_gb'] ?>GB)</option><?php endforeach; ?></select><button type="submit" class="w-full py-2.5 bg-emerald-600 text-white font-bold rounded-xl">تمدید آنی</button></form>
    </div>
</div>

<div id="editClientModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl relative max-h-[92vh] overflow-y-auto">
        <button type="button" onclick="closeEditClientModal()" class="absolute top-4 left-4 text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-lg"></i></button>
        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-pen-to-square text-amber-400"></i>ویرایش مشخصات — نام، یوزر، پسورد، سرور، پلن</h3>
        <form action="<?= Helpers::url('clients/update') ?>" method="POST" class="space-y-4 text-xs"><?= Helpers::csrfField() ?><input type="hidden" name="client_id" id="editClientId">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1 font-semibold">نام و نام خانوادگی مشتری *</label><input type="text" name="customer_name" id="editCustomerName" placeholder="مثلا: علی رضایی" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-sm focus:border-amber-500 focus:outline-none"></div>
                <div><label class="block text-slate-300 mb-1 font-semibold">وضعیت</label><select name="status" id="editStatus" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><option value="active">فعال</option><option value="disabled">غیرفعال</option><option value="expired">منقضی</option></select></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1 font-semibold">یوزرنیم *</label><input type="text" name="username" id="editUsername" required dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
                <div><label class="block text-slate-300 mb-1 font-semibold">پسورد</label><input type="text" name="password" id="editPassword" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono"></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="block text-slate-300 mb-1">سرور</label><select name="server_id" id="editServerId" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><?php foreach($servers as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-slate-300 mb-1">پلن</label><select name="plan_id" id="editPlanId" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><option value="">بدون پلن</option><?php foreach($plans as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-slate-300 mb-1">IP Limit</label><input type="number" name="ip_limit" id="editIpLimit" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-center"></div>
            </div>
            <div class="grid grid-cols-2 gap-3 p-3 bg-slate-950/60 rounded-xl border border-slate-800">
                <div><label class="block text-slate-300 mb-1">سقف حجم GB</label><input type="number" step="0.1" name="traffic_limit_gb" id="editTrafficLimitGb" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center"></div>
                <div><label class="block text-slate-300 mb-1">مصرف شده GB</label><input type="number" step="0.1" name="traffic_used_gb" id="editTrafficUsedGb" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-center"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-slate-300 mb-1">انقضا</label><input type="text" name="expire_at" id="editExpireAt" dir="ltr" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white font-mono text-xs"></div>
                <div><label class="block text-slate-300 mb-1">یادداشت</label><input type="text" name="custom_note" id="editCustomNote" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"></div>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-800"><button type="button" onclick="closeEditClientModal()" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl">انصراف</button><button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl flex items-center gap-2"><i class="fa-solid fa-floppy-disk"></i>ذخیره تغییرات</button></div>
        </form>
    </div>
</div>

<!-- Keep other modals (test, optimizer, reserve) from original - minimal versions -->
<div id="testAccountModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50"><div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 relative"><button onclick="closeTestModal()" class="absolute top-4 left-4 text-slate-400"><i class="fa-solid fa-xmark"></i></button><h3 class="font-bold text-white mb-4"><i class="fa-solid fa-wand-magic-sparkles text-cyan-400 ml-1"></i>اکانت تست</h3><form action="<?= Helpers::url('clients/test-account') ?>" method="POST" class="space-y-3 text-xs"><?= Helpers::csrfField() ?><div class="grid grid-cols-3 gap-2"><button type="button" onclick="setTestMb(200)" class="py-2 bg-slate-800 border border-slate-700 rounded-xl text-white">200MB</button><button type="button" onclick="setTestMb(500)" class="py-2 bg-slate-800 border border-slate-700 rounded-xl text-white">500MB</button><button type="button" onclick="setTestMb(1024)" class="py-2 bg-slate-800 border border-slate-700 rounded-xl text-white">1GB</button></div><input type="number" name="traffic_mb" id="test_traffic_mb" value="200" class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white text-center"><button type="submit" class="w-full py-2.5 bg-cyan-600 text-white font-bold rounded-xl">صدور تست</button></form></div></div>

<div id="optimizerModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4"><div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto relative"><button onclick="closeOptimizerModal()" class="absolute top-4 left-4 text-slate-400"><i class="fa-solid fa-xmark"></i></button><h3 class="font-bold text-white"><i class="fa-solid fa-broom text-rose-400 ml-1"></i>بهینه‌سازی و پاکسازی</h3><div class="grid grid-cols-3 gap-3 text-xs"><div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700"><div class="text-slate-400">بدون مصرف</div><div class="text-xl font-bold text-amber-300"><?= $optimizerStats['unused'] ?? 0 ?></div></div><div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700"><div class="text-slate-400">منقضی >7 روز</div><div class="text-xl font-bold text-rose-300"><?= $optimizerStats['expired_7d'] ?? 0 ?></div></div><div class="bg-slate-800/60 p-3 rounded-xl border border-slate-700"><div class="text-slate-400">کل منقضی</div><div class="text-xl font-bold text-red-400"><?= $optimizerStats['expired_all'] ?? 0 ?></div></div></div><div class="space-y-2"><form action="<?= Helpers::url('clients/optimize-purge') ?>" method="POST" class="flex justify-between items-center p-3 bg-slate-800/50 rounded-xl border border-slate-700"><?= Helpers::csrfField() ?><input type="hidden" name="purge_type" value="unused"><span class="font-bold text-white">بدون مصرف</span><button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-xs">حذف (<?= $optimizerStats['unused'] ?? 0 ?>)</button></form></div></div></div>

<div id="reserveModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4 z-50"><div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 relative"><button onclick="closeReserveModal()" class="absolute top-4 left-4 text-slate-400"><i class="fa-solid fa-xmark"></i></button><h3 class="font-bold text-white mb-4">رزرو پلن <span id="reserveUsername" class="font-mono text-cyan-400"></span></h3><form action="<?= Helpers::url('clients/reserve') ?>" method="POST" class="space-y-3 text-xs"><?= Helpers::csrfField() ?><input type="hidden" name="client_id" id="reserveClientId"><select name="plan_id" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-white"><?php foreach($plans as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?></select><button type="submit" class="w-full py-2.5 bg-cyan-600 text-white font-bold rounded-xl">ثبت رزرو</button></form></div></div>

<script>
function toggleSelectAll(m){document.querySelectorAll('.client-check').forEach(cb=>cb.checked=m.checked);}
function togglePwd(el){
    const id=el.getAttribute('data-id');
    const real=el.getAttribute('data-real');
    const span=document.getElementById('pwd_'+id);
    if(span.textContent==='••••••'){ span.textContent=real; el.innerHTML='<i class="fa-solid fa-eye-slash text-[10px]"></i>'; }
    else { span.textContent='••••••'; el.innerHTML='<i class="fa-solid fa-eye text-[10px]"></i>'; }
}
function copyDelivery(btn){
    const username = btn.getAttribute('data-username') || '';
    const password = btn.getAttribute('data-password') || '';
    const sub = btn.getAttribute('data-sub') || '';
    const customer = btn.getAttribute('data-customer') || '';
    const plan = btn.getAttribute('data-plan') || '';
    let text = '';
    if(customer) text += `👤 نام مشتری: ${customer}\n`;
    text += `👤 نام کاربری: ${username}\n`;
    text += `🔑 رمز عبور: ${password}\n`;
    if(plan) text += `📦 پلن: ${plan}\n`;
    text += `🔗 لینک سابسکریپشن:\n${sub}\n\n`;
    text += `📱 آموزش اتصال:\n1. لینک بالا را کپی کنید\n2. در اپلیکیشن Hiddify / V2rayNG گزینه Import from Clipboard را بزنید\n3. متصل شوید\n\n`;
    text += `🤖 ربات: @${window.location.hostname}bot`;
    
    navigator.clipboard.writeText(text).then(()=>{
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check text-[12px]"></i>';
        btn.classList.add('bg-emerald-600','text-white');
        setTimeout(()=>{ btn.innerHTML=orig; btn.classList.remove('bg-emerald-600','text-white'); }, 2000);
    }).catch(()=>{
        // Fallback
        const ta=document.createElement('textarea');
        ta.value=text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('✅ کپی شد:\n\n'+text);
    });
}
function openInspectModal(id,username,subUrl){
    document.getElementById('inspectUsername').innerText=username;
    document.getElementById('inspectUserField').innerText=username;
    document.getElementById('inspectSubUrl').value=subUrl;
    document.getElementById('inspectQrImage').src='https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='+encodeURIComponent(subUrl);
    fetch('<?= Helpers::url('clients/configs') ?>?id='+id).then(r=>r.json()).then(data=>{
        if(data.success){
            document.getElementById('inspectTraffic').innerText=data.client.traffic_used+' / '+data.client.traffic_limit;
            document.getElementById('inspectDays').innerText=data.client.days_remaining;
            document.getElementById('inspectUserField').innerText=data.client.username;
            document.getElementById('inspectPassField').innerText=data.client.password;
        }
    });
    const m=document.getElementById('inspectModal'); m.classList.remove('hidden'); m.classList.add('flex');
}
function closeInspectModal(){ const m=document.getElementById('inspectModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function openRenewModal(id,username){ document.getElementById('renewClientId').value=id; document.getElementById('renewUsername').innerText=username; const m=document.getElementById('renewModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeRenewModal(){ const m=document.getElementById('renewModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function openReserveModal(id,username){ document.getElementById('reserveClientId').value=id; document.getElementById('reserveUsername').innerText=username; const m=document.getElementById('reserveModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeReserveModal(){ const m=document.getElementById('reserveModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function openEditClientModal(c){
    document.getElementById('editClientId').value=c.id;
    document.getElementById('editUsername').value=c.username||'';
    document.getElementById('editPassword').value=c.password||'';
    if(document.getElementById('editCustomerName')) document.getElementById('editCustomerName').value=c.customer_name||'';
    document.getElementById('editServerId').value=c.server_id||'';
    document.getElementById('editPlanId').value=c.plan_id||'';
    document.getElementById('editStatus').value=c.status||'active';
    const limitGb=(c.traffic_limit_bytes/(1024*1024*1024)).toFixed(2);
    const usedGb=(c.traffic_used_bytes/(1024*1024*1024)).toFixed(2);
    document.getElementById('editTrafficLimitGb').value=parseFloat(limitGb);
    document.getElementById('editTrafficUsedGb').value=parseFloat(usedGb);
    document.getElementById('editExpireAt').value=c.expire_at||'';
    if(document.getElementById('editIpLimit')) document.getElementById('editIpLimit').value=c.ip_limit??0;
    if(document.getElementById('editCustomNote')) document.getElementById('editCustomNote').value=c.custom_note||'';
    const m=document.getElementById('editClientModal'); m.classList.remove('hidden'); m.classList.add('flex');
}
function closeEditClientModal(){ const m=document.getElementById('editClientModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function openTestModal(){ const m=document.getElementById('testAccountModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeTestModal(){ const m=document.getElementById('testAccountModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function setTestMb(mb){ const inp=document.getElementById('test_traffic_mb'); if(inp) inp.value=mb; }
function openOptimizerModal(){ const m=document.getElementById('optimizerModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeOptimizerModal(){ const m=document.getElementById('optimizerModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
