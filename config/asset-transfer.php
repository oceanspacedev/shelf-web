<?php

return [
    /*
     * Username akun GA bersama yang mewakili departemen, bukan orang (dulu
     * di-hardcode sebagai id 2 / "adminga"). Di PDF berita acara, pengadaan,
     * dan penyelesaian tugas, nama dan jabatannya dikosongkan supaya diisi
     * tangan oleh staf GA yang menandatangani. Pisahkan dengan koma.
     */
    'shared_general_affair_usernames' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GA_SHARED_ACCOUNT_USERNAMES', 'adminga')),
    ))),
];
