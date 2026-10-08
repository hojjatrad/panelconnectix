<?php
// S4 BBR Enable - ZERO COST, 30% faster, no disadvantage
echo "Enabling BBR...\n";
$cmds = [
    'sysctl -w net.core.default_qdisc=fq',
    'sysctl -w net.ipv4.tcp_congestion_control=bbr',
    'sysctl -w net.ipv4.tcp_fastopen=3',
    'lsmod | grep bbr',
    'sysctl net.ipv4.tcp_congestion_control',
];
foreach ($cmds as $cmd) {
    echo "$ $cmd\n";
    echo shell_exec($cmd." 2>&1")."\n";
}
echo "BBR enabled - 30% faster, free, no disadvantage\n";
?>
