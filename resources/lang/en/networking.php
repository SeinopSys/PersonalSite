<?php

return [
    'about' => 'Simple calculation tools for monotonous tasks. Supports IPv4 and IPv6.',
    'ipv4-only' => 'Only supports IPv4',
    'ipv6-only' => 'Only supports IPv6',
    'mask-table' => 'Mask table',
    'summarization' => 'Summarization',
    'prefix-list' => 'Prefix list',
    'network' => 'Network',
    'network-addr' => 'Network address',
    'shortcuts' => 'Shortcuts',
    'shortcut-clsss' => 'class range',
    'shortcut-full' => 'full range',
    'alert-placeholder' => 'Alert placeholder',
    'network-input-title' => 'Network address with subnet mask, separated by a forward slash',
    'subnets' => 'Subnets',
    'subnet-list' => 'List of subnets',
    'subnets-placeholder' =>
        '// &quot;device(s)&quot; is optional
A      20 devices  // 22 ips
B      64 ip       // 62 devices
C      40 devices  // 42 ips
VLAN10 10          // 12 ips
VLAN20 20          // 22 ips
VLAN30 30          // 32 ips
etc.',
    'subnets-title' => 'One subnet per line, format: [NAME] [NUMBER] [ip(s)/device(s)] (case-insensitive)',
    'networks' => 'Networks',
    'networks-secondary' => "One network per line, don't mix IP versions",
    'networks-placeholder' =>
        '192.168.0.0/24
192.168.20.1/20',
    'networks-title' => 'Networks incl. mask after a slash',
    'output_nav_label' => 'Output type',
    'output_fancy' => 'Table',
    'output_simple' => 'Plain text',
    'mask' => 'Mask',
    'name' => 'Name',
    'desired_subnets' => 'Number of desired subnets',
    'slash-format' => 'Slash notation',
    'dotted-decimal-format' => 'Dot-decimal',
    'reverse-decimal-format' => 'Reverse decimal',
    'show-pl-output' => 'Output of <code>show ip prefix-list NAME</code>',
    'show-pl-output-placeholder' =>
        <<<'PFX'
ip prefix-list EXAMPLE: 5 entries
   seq 5 permit 10.0.0.0/8
   seq 10 permit 10.0.0.0/8 ge 10
   seq 15 permit 172.16.0.0/16 le 20
   seq 20 permit 192.168.2.0/24 ge 25 le 26
   seq 25 deny 0.0.0.0/0 le 32
PFX
    ,
    'network-to-check' => 'Network to check',
    'detect-match' => 'Does it match?',

    'error_ipv4_only' => 'You may only use IPv4 addresses here',
    'error_ipv6_only' => 'You may only use IPv6 addresses here',

    'vlsm_error_ipadd_format_invalid' => 'The IP address :dotdec is invalid',
    'vlsm_error_ipadd_octet_invalid' => 'The :nth octet of the IP address :dotdec is invalid (:why)',
    'vlsm_error_ipadd_octet_invalid_nan' => 'not a number',
    'vlsm_error_ipadd_octet_invalid_range' => 'must be between 0-255',
    'vlsm_error_ipv6add_short_invalid' => 'The IPv6 address :colonhex contains more than one abbreviation',
    'vlsm_error_ipv6add_block_invalid' => 'The :nth hextet of the IPv6 address :colonhex is invalid (:why)',
    'vlsm_error_ipv6add_block_invalid_nan' => 'not a 16-bit hexadecimal number',
    'vlsm_error_ipv6add_block_invalid_range' => 'must be between 0-65535',
    'vlsm_error_ipv6add_too_many_blocks' => 'The IPv6 address :colonhex contains more than 8 hextets',
    'vlsm_error_mask_length_invalid' => 'Mask length is invalid (must be between :min and :max)',
    'vlsm_error_mask_length_overflow' => 'The network cannot contain this many subnets',
    'vlsm_error_subnet_line_invalid' => 'Invalid line: :line (:why)',
    'vlsm_error_subnet_line_invalid_format' => 'invalid format',
    'vlsm_error_subnet_line_invalid_count' => 'item number mismatch; did you use spaces?',
    'vlsm_subnet_tostring' => 'Subnet :name (:addresscount addresses, of which :usable is assignable)',
    'vlsm_network_too_small' => 'The requested subnets do not fit inside the specified network',
    'vlsm_info_line' => ':pcs devices (:ips IPs)',
    'vlsm_mask_reverse' => 'Reverse',

    'cidr_error_subnet_line_invalid_format' => 'The desired subnet count\'s format is invalid',

    'summary_error_mixed_ip_versions' => 'Don\'t use both IPv4 and IPv6 addresses',
    'summary_not_enough_addresses' => 'At least 2 network addresses are required to perform summarization',
    'summary_uncommon' => 'The specified addresses have no bits in common, they cannot be summarized.',

    'prefix_list_error_show_invalid' => 'The output of the show command is invalid',
    'prefix_list_error_seq_invalid' => 'Issue in entry number :seq',
    'prefix_list_error_seq_number_invalid' => 'Sequence number must be between :min and :max',
    'prefix_list_error_gtlen' => 'Invalid prefix range for :netw, make sure: :val > :len',
    'prefix_list_error_ge_le' => 'Invalid prefix range for :netw, make sure: :len < :ge <= :le',
    'prefix_list_nomatch' => 'The specified network does not match any entries',
    'prefix_list_match' => 'The specified network matches the :action entry with sequence number :seq',
];
