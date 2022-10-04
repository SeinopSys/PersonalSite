<?php

return [
    'about' => 'Egyszerű számolóeszközök monoton feladatokhoz. Kezeli az IPv4-et és az IPv6-ot.',
    'ipv4-only' => 'Csak IPv4-et támogat',
    'ipv6-only' => 'IPv6 only',
    'mask-table' => 'Maszk táblázat',
    'summarization' => 'Összevonás',
    'prefix-list' => 'Prefix lista',
    'network' => 'Hálózat',
    'network-addr' => 'Hálózati cím',
    'shortcuts' => 'Gyorsgombok',
    'shortcut-clsss' => 'oszt. maszk',
    'shortcut-full' => 'teljes tart.',
    'alert-placeholder' => 'Hibaüzenet helye',
    'network-input-title' => 'Hálózati cím perjellel elválasztott maszkkal',
    'subnets' => 'Alhálózatok',
    'subnet-list' => 'Alhálózatok listája',
    'subnets-placeholder' =>
        '// Az &quot;eszköz&quot; elhagyható
A      20 eszköz // 22 ip
B      64 ip     // 62 eszköz
C      40 eszköz // 42 ip
VLAN10 10        // 12 ip
VLAN20 20        // 22 ip
VLAN30 30        // 32 ip
és a többi&hellip;',
    'subnets-title' => 'Alhálózatok, soronként 1, [NÉV] [SZÁM] [ip/eszköz] formátumban (kis- és nagybetű nem számít)',
    'networks' => 'Hálózatok',
    'networks-secondary' => 'Soronként egy hálózat, ne keverd az IP verziókat',
    'networks-placeholder' =>
        '192.168.0.0/24
192.168.20.1/20',
    'networks-title' => 'Hálózatok perjeles maszkkal',
    'output_nav_label' => 'Eredmény megjelenése',
    'output_fancy' => 'Táblázat',
    'output_simple' => 'Egyszerű szöveg',
    'mask' => 'Maszk',
    'name' => 'Név',
    'desired_subnets' => 'Kívánt alhálózatok száma',
    'slash-format' => 'Perjeles forma',
    'dotted-decimal-format' => 'Pontozott decimális',
    'reverse-decimal-format' => 'Fordított decimális',
    'show-pl-output' => 'A <code>show ip prefix-list NÉV</code> parancs kimenete',
    'network-to-check' => 'Vizsgálni kívánt hálózat',
    'detect-match' => 'Illeszkedik-e?',

    'error_ipv4_only' => 'Itt csak IPv4-es címeket használhatsz',
    'error_ipv6_only' => 'Itt csak IPv6-es címeket használhatsz',

    'vlsm_error_ipadd_format_invalid' => 'A(z) :dotdec IP cím formátuma hibás',
    'vlsm_error_ipadd_octet_invalid' => 'A(z) :dotdec IP cím :n. octetje érvénytelen (:why)',
    'vlsm_error_ipadd_octet_invalid_nan' => 'nem szám',
    'vlsm_error_ipadd_octet_invalid_range' => 'az értéke 0-255 közt kell, hogy legyen',
    'vlsm_error_ipv6add_short_invalid' => 'A(z) :colonhex IPv6 címben egynél több rövidítés található',
    'vlsm_error_ipv6add_block_invalid' => 'A(z) :colonhex IPv6 cím :n. hextetje érvénytelen (:why)',
    'vlsm_error_ipv6add_block_invalid_nan' => 'nem egy 16-bites hexadecimális szám',
    'vlsm_error_ipv6add_block_invalid_range' => 'az értéke 0-65535 közt kell, hogy legyen',
    'vlsm_error_ipv6add_too_many_blocks' => 'A(z) :colonhex IPv6 cím 8-nál több hextetet tartalmaz',
    'vlsm_error_mask_length_invalid' => 'A maszk hossza érvénytelen (:min és :max közt kell, hogy legyen)',
    'vlsm_error_mask_length_overflow' => 'A tartomány nem osztható ennyi részre',
    'vlsm_error_subnet_line_invalid' => 'Érvénytelen sor: :line (:why)',
    'vlsm_error_subnet_line_invalid_format' => 'nem megfelelő formátum',
    'vlsm_error_subnet_line_invalid_count' => 'nem megfelelő elemszám; használtál szóközöket?',
    'vlsm_subnet_tostring' => 'Alhálózat :name (:addresscount cím, ebből :usable kisztható)',
    'vlsm_network_too_small' => 'A megadott címtartományba nem fér bele az összes kért alhálózat',
    'vlsm_info_line' => ':pcs eszköz (:ips IP)',
    'vlsm_mask_reverse' => 'Fordított',

    'cidr_error_subnet_line_invalid_format' => 'Az alhálózatok számának formátuma nem megfelelő',

    'summary_error_mixed_ip_versions' => 'Ne használj egyszerre IPv4 és IPv6-os címeket',
    'summary_not_enough_addresses' => 'Legalább 2 hálózatcím szükséges az összevonáshoz',
    'summary_uncommon' => 'A megadott címeknek nincsenek közös bitjeik, ezért nem összevonhatóak.',

    'prefix_list_error_show_invalid' => 'A show parancs kimenete érvénytelen',
    'prefix_list_error_seq_invalid' => 'Hiba a(z) :seq számú bejegyzésben',
    'prefix_list_error_seq_number_invalid' => 'A szekvenciaszám :min és :max között kell, hogy legyen',
    'prefix_list_error_gtlen' => 'Érvénytelen prefix tartomány a(z) :netw hálózathoz, ellenőrizd hogy: :val > :len',
    'prefix_list_error_ge_le' => 'Érvénytelen prefix tartomány a(z) :netw hálózathoz, ellenőrizd hogy: :len < :ge <= :le',
    'prefix_list_nomatch' => 'A megadott hálózat egyetlen bejegyzésre sem illeszkedik',
    'prefix_list_match' => 'A megadott hálózat illeszkedik a(z) :seq azonosítójú :action bejegyzésre',
];
