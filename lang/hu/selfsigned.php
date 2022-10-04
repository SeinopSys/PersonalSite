<?php

return [
    'title' => 'OpenSSL Ön-Aláírt Tanúsítvány Generátor',
    'about' => 'Generálj ön-aláírt SSL tanúsítványokat gyorsan és könnyedén',
    'description' => [
        'oprah' => 'Itt egy tanúsítvány, és neked is egy tanúsítvány, mindenki kap tanúsítványt!',
        'before_2020_01_04' => 'Ha 2020 április 1. előtt generáltál itt tanúsítványt, akkor a hozzá tartozó :pem fájl aznap lejárt. Kérlek generálj új tanúsítványt, a már frissített :pem fájlt pedig újra importáld be a rendszerbe és/vagy böngészőbe.',
        'p1' => 'Ezzel az eszközzel olyan önaláírt tanúsítványokat készíthetsz (fejlesztési célra):openssl, amelyek tartalmazzák a :san mezőt, amit a Chrome 58 már elvár.',
        'using' => ' az OpenSSL v:ver segítségével',
        'p2' => [
            'A generálás gombra kattintva kapsz egy ZIP állományt, mely tartalmaz egy tanúsítvány és egy RSA kulcs fájlt, amit egy mappába kibontva és a webszervernek megadva egyből használhatsz is. Csak ne felejtsd el hozzáadni a megbízható tanusítványokhoz a rendszeredben.',
            'Ha szükséged lenne rá, itt a PEM fájl az általános localhost kiadóhoz, amit a szerver használ',
            'lejár'
        ],
        'p3' => 'Ezt a szolgáltatást bármilyen garancia vállalása nélkül nyújtom, csak saját felelősségre használd. Mellesleg, az :xca egy nagyszerü grafikus felület ami lefedi azt, amit ez a szolgáltatást tud, szóval ha bennem nem bízol meg, próbáld ki azt.',
    ],
    'common_name' => 'Közönséges Név',
    'subdomains' => 'Al-domainek',
    'subdomains_explain' => 'Soronként egy al-domain; csak a közönséges név előtti részt add meg, pl. :long helyett :short',
    'subdomains_title' => 'Soronként egy aldomain',
    'validity' => 'Érvényesség (napokban)',

    'err' => 'A tanúsítvány generálás meghiúsult',
    'err_filefail' => 'A generált fájlok közül legalább egy nem található meg',
    'err_zipfail' => 'ZIP fájl létrehozása sikertelen (hibakód: :err)',
];
