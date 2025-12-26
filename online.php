<?php
// online.php - JSON endpoint для онлайн статистики
header('Content-Type: application/json');

$data_dir = __DIR__ . '/data';
$online_count = 0;
$active_games = 0;

if (file_exists($data_dir . '/rooms.json')) {
    $rooms_data = json_decode(file_get_contents($data_dir . '/rooms.json'), true);
    $rooms = $rooms_data['rooms'] ?? [];
    
    foreach ($rooms as $room) {
        $online_count += $room['players'];
        $active_games++;
    }
}

echo json_encode([
    'online' => $online_count,
    'active_games' => $active_games,
    'timestamp' => time()
]);
?>