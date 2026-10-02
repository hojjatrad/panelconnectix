<?php
require __DIR__ . '/../layout/header.php';
function fmtBytes($b){ $b=(int)$b; if($b<=0) return '0'; $u=['B','KB','MB','GB','TB']; $i=0; while($b>=1024 && $i<4){ $b/=1024; $i++; } return round($b,2).' '.$u[$i]; }
function gb($bytes){ return round(((int)$bytes)/1073741824,2); }
function initials($name,$user){ $name=trim($name); if($name!==''){ $parts=preg_split('/\s+/u',$name); $ini=''; foreach($parts as $p){ if($p!=='') $ini.=mb_substr($p,0,1,'UTF-8'); if(mb_strlen($ini)>=2) break; } return mb_strtoupper($ini,'UTF-8'); } return strtoupper(substr($user,0,2)); }
?>
<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm">
    <div>
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-users text-cyan-400"></i>
            <span>مدیریت کلاینت‌ها و مشترکین</span>
            <span class="px-2.5 py-0.5 rounded-full bg-purple-900/30 border border-purple-700/40 text-[11px] text-purple-300 font-mono"><?= count($clients) ?> کاربر</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">نمایش حرفه‌ای: نام مشتری + یوزر/پسورد + پلن/سرور + مصرف + انقضا — شبیه به پنل VIP Connectix</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= Helpers::url('servers/sync') ?>" class="px-3 py-2 bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 border border-purple-500/30 text-xs font-bold rounded-xl transition"><i class="fa-solid fa-cloud-arrow-down ml-1"></i>بازخوانی</a>
        <a href="<?= Helpers::url('clients/bulk') ?>" class="px-3 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow"><i class="fa-solid fa-layer-group ml-1"></i>ساخت گروهی</a>
        <a href="<?= Helpers::url('clients/create') ?>" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-lg"><i class="fa-solid fa-plus ml-1"></i>کاربر جدید</a>
    </div>
</div>

<!-- Quick Filters -->
<?php $currentFilter = $_GET['filter'] ?? ''; ?>
<div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs no-scrollbar">
    <span class="text-slate-400 font-semibold text-[11px]"><i class="fa-solid fa-bolt text-amber-400 ml-1"></i>فیلتر سریع:</span>
    <a href="<?= Helpers::url('clients') ?>" class="px-3 py-1.5 rounded-lg border <?= empty($currentFilter)?'bg-purple-600 text-white border-purple-500 font-bold':'bg-slate-900/60 text-slate-300 border-slate-800' ?>">همه</a>
    <a href="<?= Helpers::url('clients') ?>?filter=unused" class="px-3 py-1.5 rounded-lg border <?= $currentFilter==='unused'?'bg-amber-600 text-white border-amber-500':'bg-slate-900/60 text-amber-300/80 border-slate-800' ?>">بدون مصرف <span class="bg-amber-500/20 px-1 rounded"><?= $optimizerStats['unused'] ?? 0 ?></span></a>
    <a href="<?= Helpers::url('clients') ?>?filter=expired_7d" class="px-3 py-1.5 rounded-lg border <?= $currentFilter==='expired_7d'?'bg-rose-600 text-white':'bg-slate-900/60 text-rose-300/80 border-slate-800' ?>">منقضی >7 روز <span class="bg-rose-500/20 px-1 rounded"><?= $optimizerStats['expired_7d'] ?? 0 ?></span></a>
    <a href="<?= Helpers::url('clients') ?>?filter=trials" class="px-3 py-1.5 rounded-lg border <?= $currentFilter==='trials'?'bg-cyan-700 text-white':'bg-slate-900/60 text-cyan-300 border-slate-800' ?>">تست‌ها</a>
</div>

<!-- Search -->
<form method="GET" action="<?= Helpers::url('clients') ?>" class="bg-slate-900/50 p-4 rounded-xl border border-slate-800/80 text-xs grid grid-cols-1 md:grid-cols-6 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="نام مشتری، یوزرنیم، پسورد، یادداشت..." class="w-full bg-slate-800 border border-slate-700 rounded-lg pr-9 pl-3 py-2 text-white placeholder-slate-500 focus:ring-1 focus:ring-purple-500 focus:outline-none">
    </div>
    <select name="status" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white"><option value="">همه وضعیت‌ها</option><option value="active" <?= ($_GET['status']??'')==='active'?'selected':'' ?>>فعال</option><option value="expired" <?= ($_GET['status']??'')==='expired'?'selected':'' ?>>منقضی</option><option value="disabled" <?= ($_GET['status']??'')==='disabled'?'selected':'' ?>>غیرفعال</option></select>
    <select name="group" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white"><option value="">همه گروه‌ها</option><option value="default" <?= ($_GET['group']??'')==='default'?'selected':'' ?>>عادی</option><option value="economic" <?= ($_GET['group']??'')==='economic'?'selected':'' ?>>اقتصادی</option><option value="vip" <?= ($_GET['group']??'')==='vip'?'selected':'' ?>>VIP</option></select>
    <select name="plan_id" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white"><option value="">همه پلن‌ها</option><?php foreach($plans as $p): ?><option value="<?= $p['id'] ?>" <?= (int)($_GET['plan_id']??0)===$p['id']?'selected':'' ?>><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?></select>
    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-bold"><i class="fa-solid fa-filter ml-1"></i>فیلتر</button>
</form>

<!-- Bulk Toolbar -->
<form action="<?= Helpers::url('clients/bulk') ?>" method="POST" id="bulkForm">
<?= Helpers::csrfField() ?>
<div class="flex items-center justify-between bg-slate-900/60 p-3 rounded-xl border border-slate-800 text-xs">
    <div class="flex items-center gap-2"><span class="text-slate-400 font-semibold">عملیات گروهی:</span>
        <select name="bulk_action" required class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-white"><option value="">-- انتخاب --</option><option value="extend_30_days">+30 روز</option><option value="add_10_gb">+10GB</option><option value="disable">غیرفعال</option><option value="enable">فعال</option><option value="delete">حذف</option></select>
        <button type="submit" onclick="return confirm('اعمال روی انتخاب‌شده‌ها؟')" class="px-4 py-1.5 bg-purple-600 text-white font-bold rounded-lg">اعمال</button>
    </div>
    <div class="text-slate-400"><?= count($clients) ?> کاربر</div>
</div>

<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
<div class="overflow-x-auto">
<table class="w-full text-xs">
<thead class="bg-slate-800/80 text-slate-400 border-b border-slate-700/50 text-[11px] uppercase tracking-wider">
<tr>
<th class="p-3 text-center w-10"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="rounded bg-slate-800 border-slate-700 text-purple-600"></th>
<th class="p-3.5 font-semibold text-right">مشتری / احراز هویت</th>
<th class="p-3.5 font-semibold text-right">پلن / سرور</th>
<th class="p-3.5 font-semibold text-right">مصرف</th>
<th class="p-3.5 font-semibold text-right">انقضا / باقی‌مانده</th>
<th class="p-3.5 font-semibold text-right">وضعیت</th>
<th class="p-3.5 font-semibold text-center">عملیات</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-800/50">
<?php if(empty($clients)): ?><tr><td colspan="7" class="py-10 text-center text-slate-500">کاربری یافت نشد</td></tr>
<?php else: foreach($clients as $c):
$pct = $c['traffic_limit_bytes']>0 ? round(($c['traffic_used_bytes']/$c['traffic_limit_bytes'])*100,1) : 0;
$subUrl = !empty($c['node_sublink']) ? $c['node_sublink'] : Helpers::subUrl($c['sub_token']);
$daysRem = Helpers::daysRemaining($c['expire_at']); // returns like "3 روز مانده" or "منقضی"
$isExpired = str_contains($daysRem, 'منقضی') || $c['status']==='expired';
$isActive = $c['status']==='active';
$ini = initials($c['customer_name'] ?? '', $c['username']);
$barColor = $pct>=90?'bg-rose-500':($pct>=70?'bg-amber-500':'bg-emerald-500');
if($isExpired) $barColor='bg-slate-600';
$statusCfg = match($c['status']){
 'active'=>['label'=>'فعال','cls'=>'bg-emerald-500/15 text-emerald-300 border-emerald-500/30','dot'=>'bg-emerald-400'],
 'expired'=>['label'=>'منقضی','cls'=>'bg-amber-500/15 text-amber-300 border-amber-500/30','dot'=>'bg-amber-400'],
 'disabled'=>['label'=>'غیرفعال','cls'=>'bg-rose-500/15 text-rose-300 border-rose-500/30','dot'=>'bg-rose-400'],
 'limited'=>['label'=>'حجم تمام','cls'=>'bg-rose-500/15 text-rose-300 border-rose-500/30','dot'=>'bg-rose-500'],
 default=>['label'=>$c['status'],'cls'=>'bg-slate-500/15 text-slate-300 border-slate-500/30','dot'=>'bg-slate-400'],
};
?>
<tr class="hover:bg-slate-800/40 transition group">
<td class="p-3 text-center"><input type="checkbox" name="selected_ids[]" value="<?= $c['id'] ?>" class="client-check rounded bg-slate-800 border-slate-700 text-purple-600"></td>
<!-- Identity -->
<td class="p-3.5">
<div class="flex items-start gap-3">
<div class="w-9 h-9 rounded-full bg-gradient-to-br from-purple-500/20 to-indigo-600/20 border border-purple-700/30 flex items-center justify-center text-[11px] font-bold text-purple-300 shrink-0"><?= htmlspecialchars($ini) ?></div>
<div class="min-w-0 flex-1">
<?php if(!empty($c['customer_name'])): ?>
<div class="text-[13px] font-bold text-white truncate max-w-[180px]"><?= htmlspecialchars($c['customer_name']) ?></div>
<div class="flex items-center gap-2 mt-0.5">
<span class="font-mono text-[11px] text-cyan-300 bg-cyan-900/20 border border-cyan-800/30 rounded px-1.5 py-0.5" dir="ltr"><?= htmlspecialchars($c['username']) ?></span>
<span class="font-mono text-[11px] text-amber-300 bg-amber-900/20 border border-amber-800/30 rounded px-1.5 py-0.5 flex items-center gap-1" dir="ltr">
<span id="pwd_<?= $c['id'] ?>">••••••</span>
<span data-real="<?= htmlspecialchars($c['password']) ?>" data-id="<?= $c['id'] ?>" onclick="togglePwd(this)" class="cursor-pointer hover:text-amber-200"><i class="fa-solid fa-eye text-[10px]"></i></span>
<button type="button" data-copy="<?= htmlspecialchars($c['password'],ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'),this)" class="hover:text-amber-200"><i class="fa-solid fa-copy text-[9px]"></i></button>
</span>
</div>
<?php else: ?>
<div class="text-[13px] font-bold text-white font-mono" dir="ltr"><?= htmlspecialchars($c['username']) ?></div>
<div class="flex items-center gap-1 mt-1"><span class="text-[11px] text-amber-300 font-mono bg-amber-900/20 border border-amber-800/30 rounded px-1.5 py-0.5" dir="ltr"><?= htmlspecialchars($c['password']) ?></span><span class="text-[10px] text-slate-500">بدون نام مشتری</span></div>
<?php endif; ?>
<div class="text-[10px] text-slate-500 font-mono mt-1 truncate max-w-[160px]" dir="ltr"><?= htmlspecialchars(substr($c['uuid'],0,8)).'...' ?></div>
<?php if(!empty($c['node_sync'])): ?><span class="inline-block mt-1 text-[9px] px-1.5 py-0.5 rounded bg-emerald-950/60 text-emerald-300 border border-emerald-800/40"><i class="fa-solid fa-server ml-0.5"></i>مستقیم سرور</span><?php endif; ?>
<?php if(!empty($c['reseller_username'])): ?><span class="inline-block mt-1 text-[9px] px-1.5 py-0.5 rounded bg-purple-950/60 text-purple-300 border border-purple-800/40">نماینده: <?= htmlspecialchars($c['reseller_username']) ?></span><?php endif; ?>
</div>
</div>
</td>
<!-- Plan / Server -->
<td class="p-3.5">
<div class="text-[11px] text-slate-200 font-medium bg-slate-800/70 border border-slate-700/50 rounded-lg px-2 py-1 inline-block max-w-[160px] truncate"><?= htmlspecialchars($c['plan_title'] ?? 'بدون پلن') ?></div>
<div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1"><i class="fa-solid fa-server text-[9px]"></i><?= htmlspecialchars($c['server_name'] ?? 'سرور') ?></div>
<div class="flex items-center gap-1 mt-1">
<span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 border border-slate-700 text-slate-300"><?= !empty($c['ip_limit']) && (int)$c['ip_limit']>0 ? (int)$c['ip_limit'].' دستگاه' : 'نامحدود' ?></span>
<?php if(!empty($c['group_name'])): ?><span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-900/20 text-amber-300 border border-amber-700/30"><?= htmlspecialchars($c['group_name']) ?></span><?php endif; ?>
</div>
</td>
<!-- Usage -->
<td class="p-3.5 min-w-[130px]" dir="ltr">
<div class="flex items-baseline gap-1"><span class="text-slate-100 font-mono font-bold text-[12px]"><?= gb($c['traffic_used_bytes']) ?></span><span class="text-slate-500 text-[11px]">/ <?= $c['traffic_limit_bytes']>0?gb($c['traffic_limit_bytes']).' GB':'∞' ?></span></div>
<div class="w-24 h-1.5 bg-slate-800 rounded-full mt-1.5 overflow-hidden"><div class="h-full rounded-full <?= $barColor ?> transition-all" style="width:<?= min(100,$pct) ?>%"></div></div>
<div class="text-[10px] text-slate-500 mt-0.5"><?= $pct ?>% • <?= fmtBytes($c['traffic_used_bytes']) ?> / <?= fmtBytes($c['traffic_limit_bytes']) ?></div>
</td>
<!-- Expire -->
<td class="p-3.5">
<div class="font-medium text-white text-[11px]"><?= htmlspecialchars($daysRem) ?></div>
<div class="font-mono text-slate-400 text-[10px] mt-0.5" dir="ltr"><?= $c['expire_at'] ?: '∞ نامحدود' ?></div>
<?php
$remDaysNum = null;
if(preg_match('/(\d+)\s*روز/', $daysRem, $m)) $remDaysNum = (int)$m[1];
if($remDaysNum!==null){
 if($remDaysNum<=1) echo '<span class="mt-1 inline-block px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px]">امروز</span>';
 elseif($remDaysNum<=3) echo '<span class="mt-1 inline-block px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px]">'.$remDaysNum.' روز</span>';
}
?>
</td>
<!-- Status -->
<td class="p-3.5"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-bold <?= $statusCfg['cls'] ?>"><span class="w-1.5 h-1.5 rounded-full <?= $statusCfg['dot'] ?>"></span><?= $statusCfg['label'] ?></span>
<?php if(!empty($c['reserved_id'])): ?><div class="mt-1"><span class="px-2 py-0.5 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 text-[10px]">رزرو: <?= $c['reserved_gb'] ?>GB</span></div><?php endif; ?>
</td>
<!-- Actions -->
<td class="p-3.5 text-center"><div class="flex items-center justify-center gap-1 flex-wrap">
<button type="button" onclick="openInspectModal(<?= $c['id'] ?>,'<?= htmlspecialchars($c['username']) ?>','<?= $subUrl ?>')" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-purple-900/40 text-purple-300 rounded-lg border border-slate-700 transition" title="QR و ساب"><i class="fa-solid fa-qrcode text-[11px]"></i></button>
<button type="button" data-copy="<?= htmlspecialchars($subUrl,ENT_QUOTES) ?>" onclick="copyToClipboard(this.getAttribute('data-copy'),this)" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-cyan-900/40 text-cyan-300 rounded-lg border border-slate-700 transition" title="کپی ساب"><i class="fa-solid fa-link text-[11px]"></i></button>
<button type="button" onclick='openEditClientModal(<?= json_encode($c) ?>)' class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-amber-900/40 text-amber-300 rounded-lg border border-slate-700 transition" title="ویرایش"><i class="fa-solid fa-pen text-[10px]"></i></button>
<button type="button" onclick="openRenewModal(<?= $c['id'] ?>,'<?= htmlspecialchars($c['username']) ?>')" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-emerald-900/40 text-emerald-400 rounded-lg border border-slate-700 transition" title="تمدید"><i class="fa-solid fa-rotate text-[10px]"></i></button>
<button type="button" onclick="if(confirm('حذف <?= htmlspecialchars($c['username']) ?>؟')){document.getElementById('deleteIdInput').value=<?= $c['id'] ?>;document.getElementById('deleteForm').submit();}" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-rose-900/60 text-rose-400 rounded-lg border border-slate-700 transition" title="حذف"><i class="fa-solid fa-trash-can text-[10px]"></i></button>
</div></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
</div>
</form>

<form id="deleteForm" action="<?= Helpers::url('clients/delete') ?>" method="POST" class="hidden"><?= Helpers::csrfField() ?><input type="hidden" name="client_id" id="deleteIdInput"></form>

<script>
function toggleSelectAll(m){document.querySelectorAll('.client-check').forEach(cb=>cb.checked=m.checked);}
function togglePwd(el){ const id=el.getAttribute('data-id'); const real=el.getAttribute('data-real'); const span=document.getElementById('pwd_'+id); if(span.textContent==='••••••'){ span.textContent=real; el.innerHTML='<i class="fa-solid fa-eye-slash text-[10px]"></i>'; } else { span.textContent='••••••'; el.innerHTML='<i class="fa-solid fa-eye text-[10px]"></i>'; } }
function openInspectModal(id,username,subUrl){ /* existing logic - keep */ document.getElementById('inspectUsername').innerText=username; document.getElementById('inspectSubUrl').value=subUrl; document.getElementById('inspectQrImage').src='https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='+encodeURIComponent(subUrl); const m=document.getElementById('inspectModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeInspectModal(){ const m=document.getElementById('inspectModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function openRenewModal(id,username){ document.getElementById('renewClientId').value=id; document.getElementById('renewUsername').innerText=username; const m=document.getElementById('renewModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeRenewModal(){ const m=document.getElementById('renewModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
function openEditClientModal(c){ document.getElementById('editClientId').value=c.id; document.getElementById('editUsername').value=c.username||''; document.getElementById('editPassword').value=c.password||''; if(document.getElementById('editCustomerName')) document.getElementById('editCustomerName').value=c.customer_name||''; document.getElementById('editServerId').value=c.server_id||''; document.getElementById('editPlanId').value=c.plan_id||''; document.getElementById('editStatus').value=c.status||'active'; const limitGb=(c.traffic_limit_bytes/(1024*1024*1024)).toFixed(2); const usedGb=(c.traffic_used_bytes/(1024*1024*1024)).toFixed(2); document.getElementById('editTrafficLimitGb').value=parseFloat(limitGb); document.getElementById('editTrafficUsedGb').value=parseFloat(usedGb); document.getElementById('editExpireAt').value=c.expire_at||''; document.getElementById('editTelegramChatId').value=c.telegram_chat_id||''; document.getElementById('editIpLimit').value=c.ip_limit??0; document.getElementById('editCustomNote').value=c.custom_note||''; const m=document.getElementById('editClientModal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeEditClientModal(){ const m=document.getElementById('editClientModal'); m.classList.remove('flex'); m.classList.add('hidden'); }
</script>

<?php
// Include modals from original file (inspect, renew, edit, etc.) - simplified include
require __DIR__ . '/../layout/footer.php';
?>
