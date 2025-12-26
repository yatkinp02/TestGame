<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Включение отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Создаем папку rooms если её нет
$rooms_dir = __DIR__ . '/rooms';
if (!is_dir($rooms_dir)) {
    mkdir($rooms_dir, 0755, true);
    file_put_contents($rooms_dir . '/index.html', 'Access denied');
}

// Логирование запросов
$log_data = [
    'time' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'],
    'action' => $_GET['action'] ?? 'unknown',
    'room' => $_GET['room'] ?? 'none',
    'post' => $_POST
];
file_put_contents($rooms_dir . '/api_log.txt', json_encode($log_data) . "\n", FILE_APPEND);

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_state':
        getGameState();
        break;
        
    case 'update_player':
        updatePlayer();
        break;
        
    case 'join_game':
        joinGame();
        break;
        
    case 'leave_game':
        leaveGame();
        break;
        
    case 'create_room':
        createRoom();
        break;
        
    case 'check_room':
        checkRoom();
        break;
        
    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action', 'available_actions' => ['get_state', 'update_player', 'join_game', 'leave_game', 'create_room', 'check_room']]);
}

function getGameState() {
    $room_code = isset($_GET['room']) ? trim($_GET['room']) : '';
    
    if (empty($room_code)) {
        echo json_encode(['status' => 'error', 'message' => 'Room code is required']);
        return;
    }
    
    $filename = __DIR__ . "/rooms/{$room_code}.json";
    
    if (!file_exists($filename)) {
        // Создаем комнату если её нет
        $game_data = [
            'status' => 'waiting',
            'room_code' => $room_code,
            'player1' => [
                'id' => '',
                'y' => 250,
                'score' => 0,
                'ready' => false
            ],
            'player2' => [
                'id' => '',
                'y' => 250,
                'score' => 0,
                'ready' => false
            ],
            'ball' => [
                'x' => 400,
                'y' => 300,
                'dx' => 5,
                'dy' => 3
            ],
            'game_started' => false,
            'last_update' => time()
        ];
        
        file_put_contents($filename, json_encode($game_data));
        echo json_encode($game_data);
    } else {
        $game_data = json_decode(file_get_contents($filename), true);
        
        // Автоматически обновляем мяч если игра начата
        if ($game_data['game_started'] && 
            !empty($game_data['player1']['id']) && 
            !empty($game_data['player2']['id'])) {
            
            updateBallPosition($game_data);
            $game_data['last_update'] = time();
            file_put_contents($filename, json_encode($game_data));
        }
        
        echo json_encode($game_data);
    }
}

function updateBallPosition(&$game_data) {
    $ball = &$game_data['ball'];
    
    // Движение мяча
    $ball['x'] += $ball['dx'];
    $ball['y'] += $ball['dy'];
    
    // Отскок от стен
    if ($ball['y'] <= 10 || $ball['y'] >= 590) {
        $ball['dy'] *= -1;
    }
    
    // Столкновение с ракетками
    $paddle1_y = $game_data['player1']['y'];
    $paddle2_y = $game_data['player2']['y'];
    
    if ($ball['x'] <= 30 && $ball['x'] >= 20 &&
        $ball['y'] >= $paddle1_y && $ball['y'] <= $paddle1_y + 100) {
        $ball['dx'] = abs($ball['dx']);
    }
    
    if ($ball['x'] >= 770 && $ball['x'] <= 780 &&
        $ball['y'] >= $paddle2_y && $ball['y'] <= $paddle2_y + 100) {
        $ball['dx'] = -abs($ball['dx']);
    }
    
    // Гол
    if ($ball['x'] <= 0) {
        $game_data['player2']['score']++;
        resetBallPosition($ball);
    } elseif ($ball['x'] >= 800) {
        $game_data['player1']['score']++;
        resetBallPosition($ball);
    }
}

function resetBallPosition(&$ball) {
    $ball['x'] = 400;
    $ball['y'] = 300;
    $ball['dx'] = (rand(0, 1) ? 1 : -1) * 5;
    $ball['dy'] = (rand(0, 1) ? 1 : -1) * 3;
}

function updatePlayer() {
    $room_code = isset($_POST['room']) ? trim($_POST['room']) : '';
    $player_id = isset($_POST['player_id']) ? trim($_POST['player_id']) : '';
    $player_number = isset($_POST['player_number']) ? intval($_POST['player_number']) : 0;
    $y = isset($_POST['y']) ? intval($_POST['y']) : 250;
    
    if (empty($room_code) || empty($player_id) || $player_number < 1 || $player_number > 2) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        return;
    }
    
    $filename = __DIR__ . "/rooms/{$room_code}.json";
    
    if (!file_exists($filename)) {
        echo json_encode(['status' => 'error', 'message' => 'Room not found']);
        return;
    }
    
    $game_data = json_decode(file_get_contents($filename), true);
    
    // Обновляем позицию игрока
    $player_key = "player{$player_number}";
    $game_data[$player_key]['y'] = max(0, min(500, $y));
    $game_data[$player_key]['last_update'] = time();
    
    // Если игрок еще не зарегистрирован в комнате
    if (empty($game_data[$player_key]['id'])) {
        $game_data[$player_key]['id'] = $player_id;
    }
    
    // Если оба игрока подключены, начинаем игру
    if (!$game_data['game_started'] && 
        !empty($game_data['player1']['id']) && 
        !empty($game_data['player2']['id'])) {
        $game_data['game_started'] = true;
        $game_data['status'] = 'playing';
    }
    
    $game_data['last_update'] = time();
    file_put_contents($filename, json_encode($game_data));
    
    echo json_encode(['status' => 'success', 'game_state' => $game_data]);
}

function joinGame() {
    $room_code = isset($_POST['room']) ? trim($_POST['room']) : '';
    $player_id = isset($_POST['player_id']) ? trim($_POST['player_id']) : '';
    
    if (empty($room_code) || empty($player_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Room code and player ID required']);
        return;
    }
    
    $filename = __DIR__ . "/rooms/{$room_code}.json";
    
    if (!file_exists($filename)) {
        echo json_encode(['status' => 'error', 'message' => 'Room not found']);
        return;
    }
    
    $game_data = json_decode(file_get_contents($filename), true);
    
    // Определяем номер игрока
    $player_number = 0;
    
    if (empty($game_data['player1']['id'])) {
        $player_number = 1;
        $game_data['player1']['id'] = $player_id;
    } elseif (empty($game_data['player2']['id'])) {
        $player_number = 2;
        $game_data['player2']['id'] = $player_id;
    } else {
        // Проверяем если это уже подключенный игрок
        if ($game_data['player1']['id'] === $player_id) {
            $player_number = 1;
        } elseif ($game_data['player2']['id'] === $player_id) {
            $player_number = 2;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Room is full']);
            return;
        }
    }
    
    $game_data['last_update'] = time();
    file_put_contents($filename, json_encode($game_data));
    
    echo json_encode([
        'status' => 'success', 
        'player_number' => $player_number,
        'game_state' => $game_data
    ]);
}

function createRoom() {
    $room_code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $player_id = isset($_POST['player_id']) ? trim($_POST['player_id']) : '';
    
    if (empty($room_code)) {
        $room_code = strtoupper(substr(md5(uniqid()), 0, 6));
    }
    
    $game_data = [
        'status' => 'waiting',
        'room_code' => $room_code,
        'player1' => [
            'id' => $player_id,
            'y' => 250,
            'score' => 0,
            'ready' => true
        ],
        'player2' => [
            'id' => '',
            'y' => 250,
            'score' => 0,
            'ready' => false
        ],
        'ball' => [
            'x' => 400,
            'y' => 300,
            'dx' => 5,
            'dy' => 3
        ],
        'game_started' => false,
        'created_at' => time(),
        'last_update' => time()
    ];
    
    $filename = __DIR__ . "/rooms/{$room_code}.json";
    file_put_contents($filename, json_encode($game_data));
    
    echo json_encode([
        'status' => 'success',
        'room_code' => $room_code,
        'player_number' => 1,
        'game_state' => $game_data
    ]);
}

function checkRoom() {
    $room_code = isset($_GET['room']) ? trim($_GET['room']) : '';
    
    if (empty($room_code)) {
        echo json_encode(['status' => 'error', 'message' => 'Room code required']);
        return;
    }
    
    $filename = __DIR__ . "/rooms/{$room_code}.json";
    
    if (file_exists($filename)) {
        $game_data = json_decode(file_get_contents($filename), true);
        echo json_encode([
            'status' => 'success',
            'exists' => true,
            'players' => [
                'player1_connected' => !empty($game_data['player1']['id']),
                'player2_connected' => !empty($game_data['player2']['id'])
            ],
            'game_state' => $game_data
        ]);
    } else {
        echo json_encode(['status' => 'success', 'exists' => false]);
    }
}

function leaveGame() {
    $room_code = isset($_POST['room']) ? trim($_POST['room']) : '';
    $player_id = isset($_POST['player_id']) ? trim($_POST['player_id']) : '';
    
    if (empty($room_code)) {
        echo json_encode(['status' => 'error', 'message' => 'Room code required']);
        return;
    }
    
    $filename = __DIR__ . "/rooms/{$room_code}.json";
    
    if (file_exists($filename)) {
        $game_data = json_decode(file_get_contents($filename), true);
        
        // Удаляем игрока
        if ($game_data['player1']['id'] === $player_id) {
            $game_data['player1']['id'] = '';
            $game_data['player1']['ready'] = false;
        } elseif ($game_data['player2']['id'] === $player_id) {
            $game_data['player2']['id'] = '';
            $game_data['player2']['ready'] = false;
        }
        
        // Если оба игрока вышли, удаляем комнату
        if (empty($game_data['player1']['id']) && empty($game_data['player2']['id'])) {
            unlink($filename);
            echo json_encode(['status' => 'success', 'room_deleted' => true]);
        } else {
            $game_data['game_started'] = false;
            $game_data['status'] = 'waiting';
            file_put_contents($filename, json_encode($game_data));
            echo json_encode(['status' => 'success', 'room_deleted' => false]);
        }
    } else {
        echo json_encode(['status' => 'success', 'room_deleted' => true]);
    }
}
?>