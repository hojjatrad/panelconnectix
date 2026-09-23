<?php
$title = 'نتیجه صدور گروهی اکانت‌ها';
require __DIR__ . '/../layout/header.php';
?>

<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400"></i>
                <span>تعداد <?= count($accounts) ?> اکانت با موفقیت صادر شد</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">اکانت‌ها روی سرور ثبت و در دیتابیس پنل ذخیره گردیدند.</p>
        </div>

        <div class="flex items-center gap-2">
            <button onclick="downloadCSV()" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow">
                <i class="fa-solid fa-file-excel"></i>
                <span>دانلود اکسل (CSV)</span>
            </button>
            <button onclick="downloadTXT()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-slate-700">
                <i class="fa-solid fa-file-lines"></i>
                <span>دانلود متنی (TXT)</span>
            </button>
            <button onclick="copyAllLinks()" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow">
                <i class="fa-solid fa-copy"></i>
                <span>کپی همه لینک‌ها</span>
            </button>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead>
                    <tr class="bg-slate-950/60 text-slate-400 border-b border-slate-800 font-bold">
                        <th class="p-3.5 pr-5">#</th>
                        <th class="p-3.5">نام کاربری</th>
                        <th class="p-3.5">کلمه عبور</th>
                        <th class="p-3.5">حجم پلن</th>
                        <th class="p-3.5">تاریخ انقضا</th>
                        <th class="p-3.5 text-center">لینک ساب‌لینک</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono text-slate-300">
                    <?php foreach ($accounts as $idx => $a): ?>
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="p-3.5 pr-5 text-slate-500 font-sans"><?= $idx + 1 ?></td>
                            <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($a['username']) ?></td>
                            <td class="p-3.5 text-slate-400"><?= htmlspecialchars($a['password']) ?></td>
                            <td class="p-3.5 font-sans text-cyan-400"><?= $a['traffic_gb'] ?> GB</td>
                            <td class="p-3.5 text-slate-400 font-sans"><?= substr($a['expire_at'], 0, 10) ?></td>
                            <td class="p-3.5 text-center font-sans">
                                <button onclick="navigator.clipboard.writeText('<?= $a['sub_url'] ?>'); this.innerText = 'کپی شد!';" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded text-[11px] border border-slate-700 transition">
                                    کپی ساب‌لینک
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const accountsData = <?= json_encode($accounts, JSON_UNESCAPED_UNICODE) ?>;

function downloadCSV() {
    let csv = "\uFEFFنام کاربری,کلمه عبور,حجم(GB),تاریخ انقضا,لینک ساب‌لینک\n";
    accountsData.forEach(a => {
        csv += `"${a.username}","${a.password}","${a.traffic_gb}","${a.expire_at}","${a.sub_url}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'bulk_accounts_' + Date.now() + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function downloadTXT() {
    let txt = "لیست اکانت‌های صادر شده - سامانه کانکتیکس\n==========================================\n\n";
    accountsData.forEach((a, i) => {
        txt += `${i + 1}. Username: ${a.username} | Password: ${a.password} | Traffic: ${a.traffic_gb}GB\nSublink: ${a.sub_url}\n------------------------------------------\n`;
    });
    const blob = new Blob([txt], { type: 'text/plain;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'bulk_accounts_' + Date.now() + '.txt');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function copyAllLinks() {
    let all = accountsData.map(a => a.sub_url).join("\n");
    navigator.clipboard.writeText(all).then(() => {
        alert('تمامی لینک‌های اتصال در حافظه کپی شدند.');
    });
}
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
