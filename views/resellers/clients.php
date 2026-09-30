<?php
require __DIR__ . '/../layout/header.php';
$hasReseller = !empty($reseller);

function initialsReseller($name,$user){
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
function gbR($b){ return round(((int)$b)/1073741824,2); }
?>

<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm mb-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-600/20 to-purple-600/20 border border-indigo-700/30 flex items-center justify-center text-indigo-300">
            <i class="fa-solid fa-users-gear text-lg"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <?php if ($hasReseller): ?>
                    <span>کلاینت‌های نماینده: <span class="font-mono text-purple-300"><?= htmlspecialchars($reseller['username']) ?></span></span>
                    <span class="px-2.5 py-0.5 rounded-full bg-purple-900/30 border border-purple-700/40 text-[11px] text-purple-300"><?= htmlspecialchars($reseller['brand_name'] ?? $reseller['full_name']) ?></span>
                <?php else: ?>
                    <span>نظارت بر کلاینت‌های کلیه نمایندگان</span>
                    <span class="px-2.5 py-0.5 rounded-full bg-indigo-900/30 border border-indigo-700/40 text-[11px] text-indigo-300 font-mono"><?= count($clients) ?> کلاینت</span>
                <?php endif; ?>
                <span class="px-2 py-0.5 rounded-full bg-emerald-900/30 border border-emerald-700/40 text-[10px] text-emerald-300">حرفه‌ای - هماهنگ با پنل اصلی</span>
            </h2>
            <p class="text-xs text-slate-400 mt-1">نمایش: نام مشتری + یوزر/پسورد با چشم + پلن/سرور + مصرف + انقضا — هماهنگ با پنل اصلی و VIP</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET" action="<?= Helpers::url('resellers/clients') ?>" class="m-0 flex items-center gap-2">
            <select name="id" onchange="this.form.submit()" class="bg-slate-800 border border-slate-700 rounded-xl p-2.5 text-xs text-white min-w-[180px]">
                <option value="0">🌐 همه نمایندگان (<?= count($clients) ?>)</option>
                <?php if (!empty($allResellers)): foreach ($allResellers as $ar): ?>
                    <option value="<?= $ar['id'] ?>" <?= ($resellerId === (int)$ar['id']) ? 'selected' : '' ?>>👤 <?= htmlspecialchars($ar['username']) ?> (<?= htmlspecialchars($ar['brand_name'] ?: $ar['full_name']) ?>)</option>
                <?php endforeach; endif; ?>
            </select>
        </form>
        <a href="<?= Helpers::url('resellers') ?>" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition"><i class="fa-solid fa-arrow-right ml-1"></i>نمایندگان</a>
    </div>
</div>

<?php if ($hasReseller): ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4"><span class="text-slate-400 text-xs block">کل کلاینت‌ها</span><span class="text-xl font-bold text-white font-mono mt-1 block"><?= count($clients) ?></span></div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4"><span class="text-slate-400 text-xs block">کیف پول</span><span class="text-xl font-bold text-emerald-400 font-mono mt-1 block"><?= Helpers::formatMoney($reseller['wallet_balance']) ?></span></div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4"><span class="text-slate-400 text-xs block">اعتبار بدهی</span><span class="text-xl font-bold text-purple-400 font-mono mt-1 block"><?= Helpers::formatMoney($reseller['credit_limit'] ?? 0) ?></span></div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4"><span class="text-slate-400 text-xs block">تخفیف</span><span class="text-xl font-bold text-cyan-400 font-mono mt-1 block"><?= $reseller['discount_percent'] ?>%</span></div>
</div>
<?php else: ?>
<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4"><span class="text-slate-400 text-xs block">کل کلاینت‌های نمایندگان</span><span class="text-xl font-bold text-white font-mono mt-1 block"><?= count($clients) ?> کلاینت</span></div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4"><span class="text-slate-400 text-xs block">نمایندگان فعال</span><span class="text-xl font-bold text-cyan-400 font-mono mt-1 block"><?= count($allResellers ?? []) ?> نماینده</span></div>
    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4"><span class="text-slate-400 text-xs block">فعال اکنون</span><span class="text-xl font-bold text-emerald-400 font-mono mt-1 block"><?= count(array_filter($clients, fn($c) => $c['status'] === 'active')) ?> کلاینت</span></div>
</div>
<?php endif; ?>

<!-- Search -->
<div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3 mb-4 flex gap-2">
    <div class="relative flex-1">
        <i class="fa-solid fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
        <input type="text" id="reseller_search" placeholder="جستجو: نام مشتری، یوزرنیم، پسورد، پلن، سرور..." class="w-full bg-slate-800 border border-slate-700 rounded-xl pr-9 pl-4 py-2.5 text-white text-xs focus:outline-none focus:ring-1 focus:ring-indigo-500" oninput="filterResellerClients()">
    </div>
    <select id="reseller_status_filter" onchange="filterResellerClients()" class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-slate-300">
        <option value="">همه وضعیت‌ها</option>
        <option value="active">فعال</option>
        <option value="expired">منقضی</option>
        <option value="limited">حجم تمام</option>
    </select>
</div>

<div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
    <?php if (empty($clients)): ?>
        <div class="p-12 text-center text-slate-400 space-y-2"><i class="fa-solid fa-user-slash text-4xl text-slate-600 block"></i><p class="text-sm">کلاینتی یافت نشد.</p></div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-slate-800/80 text-slate-400 border-b border-slate-700/50 text-[11px] uppercase tracking-wider">
                    <tr>
                        <th class="p-3.5 font-bold">ردیف</th>
                        <th class="p-3.5 font-bold">مشتری / احراز هویت</th>
                        <?php if (!$hasReseller): ?><th class="p-3.5 font-bold">نماینده</th><?php endif; ?>
                        <th class="p-3.5 font-bold">پلن / سرور</th>
                        <th class="p-3.5 font-bold">مصرف</th>
                        <th class="p-3.5 font-bold">انقضا / باقی‌مانده</th>
                        <th class="p-3.5 font-bold">وضعیت</th>
                        <th class="p-3.5 font-bold text-center">ساب‌لینک</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50" id="reseller_clients_body">
                    <?php foreach ($clients as $idx => $c):
                        $percent = ($c['traffic_limit_bytes'] > 0) ? min(100, round(($c['traffic_used_bytes'] / $c['traffic_limit_bytes']) * 100)) : 0;
                        $subUrl = Helpers::subUrl($c['sub_token']);
                        $daysRem = Helpers::daysRemaining($c['expire_at']);
                        $isExpired = str_contains($daysRem, 'منقضی') || $c['status']==='expired';
                        $ini = initialsReseller($c['customer_name'] ?? '', $c['username']);
                        $barColor = $percent>=90?'bg-rose-500':($percent>=70?'bg-amber-500':'bg-emerald-500');
                        if($isExpired) $barColor='bg-slate-600';
                        $statusCfg = match($c['status']){
                            'active'=>['label'=>'فعال','cls'=>'bg-emerald-500/15 text-emerald-300 border-emerald-500/30','dot'=>'bg-emerald-400'],
                            'expired'=>['label'=>'منقضی','cls'=>'bg-amber-500/15 text-amber-300 border-amber-500/30','dot'=>'bg-amber-400'],
                            'disabled'=>['label'=>'غیرفعال','cls'=>'bg-rose-500/15 text-rose-300 border-rose-500/30','dot'=>'bg-rose-400'],
                            default=>['label'=>htmlspecialchars($c['status']),'cls'=>'bg-slate-500/15 text-slate-300 border-slate-500/30','dot'=>'bg-slate-400'],
                        };
                        $remBadge='';
                        if(preg_match('/(\d+)\s*روز/', $daysRem, $m)){
                            $d=(int)$m[1];
                            if($d<=1) $remBadge='<span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px]">امروز</span>';
                            elseif($d<=3) $remBadge='<span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[10px]">'.$d.' روز</span>';
                            else $remBadge='<span class="px-2 py-0.5 rounded-full bg-slate-700 text-slate-300 border border-slate-600 text-[10px]">'.$d.' روز</span>';
                        } elseif($isExpired){
                            $remBadge='<span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[10px]">منقضی</span>';
                        }
                    ?>
                        <tr class="hover:bg-slate-800/40 transition" data-search="<?= htmlspecialchars(strtolower(($c['customer_name']??'').' '.$c['username'].' '.($c['plan_title']??'').' '.($c['server_name']??'').' '.($c['reseller_username']??'').' '.$c['status'])) ?>" data-status="<?= $c['status'] ?>">
                            <td class="p-3.5 font-mono text-slate-500"><?= $idx + 1 ?></td>
                            <td class="p-3.5">
                                <div class="flex items-start gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500/20 to-purple-600/20 border border-indigo-700/30 flex items-center justify-center text-[11px] font-bold text-indigo-300 shrink-0"><?= htmlspecialchars($ini) ?></div>
                                    <div class="min-w-0 flex-1">
                                        <?php if(!empty($c['customer_name'])): ?>
                                            <div class="text-[13px] font-bold text-white truncate max-w-[180px]" title="<?= htmlspecialchars($c['customer_name']) ?>"><?= htmlspecialchars($c['customer_name']) ?></div>
                                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                                <span class="inline-flex items-center gap-1 font-mono text-[10px] text-cyan-300 bg-cyan-950/40 border border-cyan-800/40 rounded-lg px-2 py-0.5" dir="ltr"><?= htmlspecialchars($c['username']) ?></span>
                                                <?php if(!empty($c['password'])): ?>
                                                <span class="inline-flex items-center gap-1 font-mono text-[10px] text-amber-300 bg-amber-950/30 border border-amber-800/30 rounded-lg px-2 py-0.5" dir="ltr">
                                                    <span id="rpwd_<?= $c['id'] ?>">••••••</span>
                                                    <span data-real="<?= htmlspecialchars($c['password'],ENT_QUOTES) ?>" data-id="<?= $c['id'] ?>" onclick="toggleResellerPwd(this)" class="cursor-pointer hover:text-amber-100"><i class="fa-solid fa-eye text-[9px]"></i></span>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-[13px] font-bold text-white font-mono" dir="ltr"><?= htmlspecialchars($c['username']) ?></div>
                                            <div class="text-[10px] text-amber-400/60 mt-1">بدون نام مشتری</div>
                                        <?php endif; ?>
                                        <div class="text-[10px] text-slate-500 font-mono mt-1 truncate max-w-[140px]" dir="ltr"><?= htmlspecialchars(substr($c['uuid'] ?? '',0,8)) ?>...</div>
                                    </div>
                                </div>
                            </td>
                            <?php if (!$hasReseller): ?>
                            <td class="p-3.5">
                                <div class="text-[12px] font-bold text-indigo-300"><?= htmlspecialchars($c['reseller_username'] ?? 'مدیر') ?></div>
                                <div class="text-[10px] text-slate-500 truncate max-w-[120px]"><?= htmlspecialchars($c['reseller_brand'] ?? '') ?></div>
                            </td>
                            <?php endif; ?>
                            <td class="p-3.5">
                                <div class="text-[11px] font-bold text-slate-100 bg-slate-800/80 border border-slate-700/60 rounded-lg px-2.5 py-1 inline-flex items-center gap-1.5 max-w-[160px] truncate"><i class="fa-solid fa-box text-purple-400 text-[10px]"></i><?= htmlspecialchars($c['plan_title'] ?? 'شخصی') ?></div>
                                <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1"><i class="fa-solid fa-server text-[9px] text-cyan-400"></i><?= htmlspecialchars($c['server_name'] ?? 'نامشخص') ?></div>
                            </td>
                            <td class="p-3.5" dir="ltr">
                                <div class="flex items-baseline gap-1"><span class="text-slate-100 font-mono font-bold text-[12px]"><?= gbR($c['traffic_used_bytes']) ?></span><span class="text-slate-500 text-[10px]">/ <?= $c['traffic_limit_bytes']>0?gbR($c['traffic_limit_bytes']).' GB':'∞' ?></span></div>
                                <div class="w-24 h-1.5 bg-slate-800 rounded-full mt-1.5 overflow-hidden border border-slate-700/30"><div class="h-full rounded-full <?= $barColor ?>" style="width:<?= $percent ?>%"></div></div>
                                <div class="text-[10px] text-slate-500 mt-0.5"><?= $percent ?>%</div>
                            </td>
                            <td class="p-3.5">
                                <div class="text-[11px] font-bold text-white"><?= htmlspecialchars($daysRem) ?></div>
                                <div class="font-mono text-slate-500 text-[10px] mt-0.5" dir="ltr"><?= $c['expire_at'] ?: '∞' ?></div>
                                <div class="mt-1"><?= $remBadge ?></div>
                            </td>
                            <td class="p-3.5"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-bold <?= $statusCfg['cls'] ?>"><span class="w-1.5 h-1.5 rounded-full <?= $statusCfg['dot'] ?>"></span><?= $statusCfg['label'] ?></span></td>
                            <td class="p-3.5 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button onclick="copyToClipboard('<?= $subUrl ?>', this)" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-cyan-900/40 text-cyan-300 rounded-lg border border-slate-700 transition" title="کپی ساب"><i class="fa-solid fa-copy text-[11px]"></i></button>
                                    <a href="<?= $subUrl ?>" target="_blank" class="w-7 h-7 flex items-center justify-center bg-slate-800 hover:bg-indigo-900/40 text-indigo-300 rounded-lg border border-slate-700 transition" title="باز کردن"><i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleResellerPwd(el){
    const id=el.getAttribute('data-id');
    const real=el.getAttribute('data-real');
    const span=document.getElementById('rpwd_'+id);
    if(span.textContent==='••••••'){ span.textContent=real; el.innerHTML='<i class="fa-solid fa-eye-slash text-[9px]"></i>'; }
    else { span.textContent='••••••'; el.innerHTML='<i class="fa-solid fa-eye text-[9px]"></i>'; }
}
function filterResellerClients(){
    const q=(document.getElementById('reseller_search').value||'').toLowerCase();
    const statusF=document.getElementById('reseller_status_filter')?.value||'';
    document.querySelectorAll('#reseller_clients_body tr').forEach(tr=>{
        const s=(tr.getAttribute('data-search')||'')+' '+(tr.innerText||'').toLowerCase();
        const st=tr.getAttribute('data-status')||'';
        const matchQ=s.includes(q);
        const matchS=!statusF||st===statusF;
        tr.style.display=(matchQ&&matchS)?'':'none';
    });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
