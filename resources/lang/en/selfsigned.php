<?php

return [
    'title' => 'OpenSSL Self-Signed Certificate Generator',
    'about' => 'Quickly and easily generate self-signed SSL certificates',
    'description' => [
        'oprah' => 'You get a cert, and you get a cert, everybody gets a cert!',
        'before_2020_01_04' => 'If you used this site to generate certificates before 1st April 2020 then the previous :pem file expired as of that date. Please generate the certificate again and re-import the new :pem file into your system and/or browser.',
        'p1' => 'This tool lets you generate self-signed certificates (for development use):openssl that contain the :san which Chrome 58 now requires.',
        'using' => ' using OpenSSL v:ver',
        'p2' => [
            "When you click generate you'll get a ZIP archive with a certificate and RSA key file that you can simply extract into a folder and use in your web server setup. Just make sure to add it to the certificates that the system trusts.",
            "In case you need it, here's the PEM file for the generic localhost CA this server uses",
            'expires'
        ],
        'p3' => "This service is provided as-is without any liability or warranty, use at your own risk. By the way, :xca is a great GUI that allows you to do the same this service does, so you can give it a try if you don't trust me.",
    ],
    'common_name' => 'Common Name',
    'subdomains' => 'Subdomains',
    'subdomains_explain' => 'One per line; only enter the part before the common name, e.g. :short instead of :long',
    'subdomains_title' => 'One subdomain per line',
    'validity' => 'Valid for (days)',

    'err' => 'Certificate generation failed',
    'err_filefail' => 'At least one of the generated files cannot be found',
    'err_zipfail' => 'Could not create a ZIP archive (code: :err)',
];
