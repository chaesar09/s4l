<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Since you don't have a news table in your database, we'll create a mock news array
// In a real implementation, you would create a news table in your database

 $news = [
    [
        'id' => 1,
        'title' => 'Update Season 7: Neon Revolution',
        'content' => 'Senjata baru, map baru, dan event terbaru telah hadir di S4 League!',
        'category' => 'PATCH NOTES',
        'image_url' => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=800',
        'created_at' => '2023-05-15'
    ],
    [
        'id' => 2,
        'title' => 'Double AP & PEN Weekend',
        'content' => 'Dapatkan double AP dan PEN semua mode game akhir pekan ini!',
        'category' => 'EVENT',
        'image_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&w=800',
        'created_at' => '2023-05-10'
    ],
    [
        'id' => 3,
        'title' => 'Kolaborasi dengan Cyberpunk 2077',
        'content' => 'Kostum eksklusif dari dunia Cyberpunk 2077 sekarang tersedia!',
        'category' => 'KOLABORASI',
        'image_url' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&w=800',
        'created_at' => '2023-05-05'
    ],
    [
        'id' => 4,
        'title' => 'S4 League Championship 2023',
        'content' => 'Pendaftaran turnamen tahunan telah dibuka! Total hadiah 100 juta rupiah!',
        'category' => 'TURNAMEN',
        'image_url' => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&w=800',
        'created_at' => '2023-05-01'
    ]
];

sendResponse(true, 'Berita berhasil diambil', $news);
?>