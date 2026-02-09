<?php
return [
    'olt-1' => [
        'name'     => 'your_olt_name',
        'host'     => 'your_olt_ip',
        'port'     => 23,
        'username' => 'your_username',
        'password' => 'your_password',
        // Set false if your OLT does not need `enable` mode.
        // 'enable' => true,
        // Some OLTs prompt "Password:" after `enable`. If different from login password, set it here.
        // 'enable_password' => 'your_enable_password',
        // Optional: increase when OLT CLI is slow (seconds)
        // 'cmd_timeout' => 20,
        // Optional: override CLI commands when firmware differs
        // 'onu_list_cmd' => 'show onu info epon 0/1 all',
        // 'onu_ddm_cmd'  => 'show onu optical-ddm epon 0/1 1',
        'pons'     => ['0/1', '0/2', '0/3', '0/4'],
    ],

    'olt-2' => [
        'name'     => 'your_olt_name',
        'host'     => 'your_olt_ip',
        'port'     => 23,
        'username' => 'your_username',
        'password' => 'your_password',
        'pons'     => ['0/1', '0/2', '0/3', '0/4'],
    ],
];
