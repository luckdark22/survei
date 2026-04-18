<?php
/**
 * Updated dummy questions with 4 levels of satisfaction
 */

$questions = [
    [
        'id' => 1,
        'type' => 'rating',
        'section' => 'SERVICE QUALITY',
        'question' => 'Seberapa puas Anda dengan kualitas pelayanan di kantor wilayah kami?',
        'options' => [
            ['value' => 'sangat_puas', 'label' => 'SANGAT PUAS', 'image' => 'assets/img/sangat_puas.png', 'color' => '#f59e0b'], 
            ['value' => 'puas', 'label' => 'PUAS', 'image' => 'assets/img/puas.png', 'color' => '#fbbf24'], 
            ['value' => 'cukup_puas', 'label' => 'CUKUP PUAS', 'image' => 'assets/img/cukup_puas.png', 'color' => '#fcd34d'], 
            ['value' => 'tidak_puas', 'label' => 'TIDAK PUAS', 'image' => 'assets/img/tidak_puas.png', 'color' => '#f87171'], 
        ]
    ],
    [
        'id' => 2,
        'type' => 'rating',
        'section' => 'FACILITIES',
        'question' => 'Bagaimana penilaian Anda terhadap kenyamanan fasilitas pendukung kami?',
        'options' => [
            ['value' => 'sangat_puas', 'label' => 'SANGAT PUAS', 'image' => 'assets/img/sangat_puas.png', 'color' => '#f59e0b'],
            ['value' => 'puas', 'label' => 'PUAS', 'image' => 'assets/img/puas.png', 'color' => '#fbbf24'],
            ['value' => 'cukup_puas', 'label' => 'CUKUP PUAS', 'image' => 'assets/img/cukup_puas.png', 'color' => '#fcd34d'],
            ['value' => 'tidak_puas', 'label' => 'TIDAK PUAS', 'image' => 'assets/img/tidak_puas.png', 'color' => '#f87171'],
        ]
    ],
    [
        'id' => 3,
        'type' => 'rating',
        'section' => 'STAFFING',
        'question' => 'Apakah petugas kami melayani Anda dengan ramah dan solutif?',
        'options' => [
            ['value' => 'sangat_puas', 'label' => 'SANGAT PUAS', 'image' => 'assets/img/sangat_puas.png', 'color' => '#f59e0b'],
            ['value' => 'puas', 'label' => 'PUAS', 'image' => 'assets/img/puas.png', 'color' => '#fbbf24'],
            ['value' => 'cukup_puas', 'label' => 'CUKUP PUAS', 'image' => 'assets/img/cukup_puas.png', 'color' => '#fcd34d'],
            ['value' => 'tidak_puas', 'label' => 'TIDAK PUAS', 'image' => 'assets/img/tidak_puas.png', 'color' => '#f87171'],
        ]
    ],
    [
        'id' => 4,
        'type' => 'text',
        'section' => 'FEEDBACK',
        'question' => 'Ada saran atau masukan lain agar kami bisa melayani lebih baik?',
        'placeholder' => 'Ketikkan aspirasi Anda di sini...'
    ]
];
