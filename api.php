<?php
session_start();

// Настройки для Render
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Определяем корневую директорию
define('ROOT_DIR', __DIR__);

// Создаем папку для данных
$data_dir = ROOT_DIR . '/data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// Включение логирования
$log_file = $data_dir . '/debug.log';
$log_entry = date('Y-m-d H:i:s') . " | Action: " . ($_GET['action'] ?? 'none') . 
             " | Room: " . ($_GET['room'] ?? $_POST['room'] ?? 'none') . 
             " | Player: " . ($_POST['player_id'] ?? 'none') . "\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Обработка OPTIONS запроса (для CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    case 'create_game':
        createGame();
        break;
    case 'check_game':
        checkGame();
        break;
    case 'leave_game':
        leaveGame();
        break;
    default:
        echo json_encode(['status' => 'ready', 'message' => 'Pong Multiplayer API', 'time' => time()]);
}

function getGameState() {
    $room_code = trim($_GET['room'] ?? '');
    
    if (empty($room_code)) {
        echo json_encode(['error' => 'Room code required', 'status' => 'error']);
        return;
    }
    
    $filename = ROOT_DIR . "/data/{$room_code}.json";
    
    if (!file_exists($filename)) {
        // Создаем комнату по умолчанию
        $game_data = [
            'room_code' => $room_code,
            'status' => 'waiting',
            'player1' => ['id' => '', 'y' => 250, 'score' => 0, 'ready' => false],
            'player2' => ['id' => '', 'y' => 250, 'score' => 0, 'ready' => false],
            'ball' => ['x' => 400, 'y' => 300, 'dx' => 5, 'dy' => 3],
            'game_started' => false,
            'last_update' => time(),
            'created_at' => time()
        ];
        
        file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
    } else {
        $game_data = json_decode(file_get_contents($filename), true);
        
        // Автоматически обновляем состояние игры
        if ($game_data['game_started'] && !empty($game_data['player1']['id']) && !empty($game_data['player2']['id'])) {
            updateGamePhysics($game_data);
            $game_data['last_update'] = time();
            file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
        }
    }
    
    echo json_encode($game_data);
}

function updateGamePhysics(&$game_data) {
    $ball = &$game_data['ball'];
    
    // Движение мяча
    $ball['x'] += $ball['dx'];
    $ball['y'] += $ball['dy'];
    
    // Отскок от верхней/нижней стен
    if ($ball['y'] <= 0 || $ball['y'] >= 600) {
        $ball['dy'] *= -1;
    }
    
    // Столкновение с ракетками
    $p1_y = $game_data['player1']['y'];
    $p2_y = $game_data['player2']['y'];
    
    // Ракетка игрока 1 (левая)
    if ($ball['x'] <= 30 && $ball['x'] >= 20 && 
        $ball['y'] >= $p1_y && $ball['y'] <= $p1_y + 100) {
        $ball['dx'] = abs($ball['dx']);
        $ball['dx'] *= 1.05; // Ускорение
    }
    
    // Ракетка игрока 2 (правая)
    if ($ball['x'] >= 770 && $ball['x'] <= 780 && 
        $ball['y'] >= $p2_y && $ball['y'] <= $p2_y + 100) {
        $ball['dx'] = -abs($ball['dx']);
        $ball['dx'] *= 1.05; // Ускорение
    }
    
    // Гол
    if ($ball['x'] < 0) {
        $game_data['player2']['score']++;
        resetBall($ball);
    } elseif ($ball['x'] > 800) {
        $game_data['player1']['score']++;
        resetBall($ball);
    }
}

function resetBall(&$ball) {
    $ball['x'] = 400;
    $ball['y'] = 300;
    $ball['dx'] = (rand(0, 1) ? 1 : -1) * 5;
    $ball['dy'] = (rand(0, 1) ? 1 : -1) * 3;
}

function updatePlayer() {
    $room_code = trim($_POST['room'] ?? '');
    $player_id = trim($_POST['player_id'] ?? '');
    $player_number = intval($_POST['player_number'] ?? 0);
    $y = intval($_POST['y'] ?? 250);
    
    if (empty($room_code) || empty($player_id) || $player_number < 1 || $player_number > 2) {
        echo json_encode(['error' => 'Invalid parameters', 'status' => 'error']);
        return;
    }
    
    $filename = ROOT_DIR . "/data/{$room_code}.json";
    
    if (!file_exists($filename)) {
        echo json_encode(['error' => 'Room not found', 'status' => 'error']);
        return;
    }
    
    $game_data = json_decode(file_get_contents($filename), true);
    
    // Обновляем позицию игрока
    $player_key = "player{$player_number}";
    $game_data[$player_key]['y'] = max(0, min(500, $y));
    $game_data[$player_key]['last_move'] = time();
    
    // Если игрок не зарегистрирован в комнате, регистрируем
    if (empty($game_data[$player_key]['id'])) {
        $game_data[$player_key]['id'] = $player_id;
        $game_data[$player_key]['ready'] = true;
    }
    
    // Запускаем игру если оба игрока готовы
    if (!$game_data['game_started'] && 
        !empty($game_data['player1']['id']) && 
        !empty($game_data['player2']['id'])) {
        $game_data['game_started'] = true;
        $game_data['status'] = 'playing';
        $game_data['started_at'] = time();
    }
    
    $game_data['last_update'] = time();
    file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Player position updated',
        'game_state' => $game_data
    ]);
}

function joinGame() {
    $room_code = trim($_POST['room'] ?? '');
    $player_id = trim($_POST['player_id'] ?? '');
    
    if (empty($room_code) || empty($player_id)) {
        echo json_encode(['error' => 'Room code and player ID required', 'status' => 'error']);
        return;
    }
    
    $filename = ROOT_DIR . "/data/{$room_code}.json";
    
    if (!file_exists($filename)) {
        echo json_encode(['error' => 'Room not found', 'status' => 'error']);
        return;
    }
    
    $game_data = json_decode(file_get_contents($filename), true);
    
    // Проверяем, не подключен ли уже этот игрок
    if ($game_data['player1']['id'] === $player_id) {
        echo json_encode([
            'status' => 'success',
            'player_number' => 1,
            'message' => 'Already connected as player 1',
            'game_state' => $game_data
        ]);
        return;
    }
    
    if ($game_data['player2']['id'] === $player_id) {
        echo json_encode([
            'status' => 'success',
            'player_number' => 2,
            'message' => 'Already connected as player 2',
            'game_state' => $game_data
        ]);
        return;
    }
    
    // Подключаем как игрока 2 если место свободно
    if (empty($game_data['player2']['id'])) {
        $game_data['player2']['id'] = $player_id;
        $game_data['player2']['ready'] = true;
        $game_data['status'] = 'both_players_ready';
        $game_data['last_update'] = time();
        
        file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'status' => 'success',
            'player_number' => 2,
            'message' => 'Joined as player 2',
            'game_state' => $game_data
        ]);
    } else {
        echo json_encode(['error' => 'Room is full', 'status' => 'error']);
    }
}

function createGame() {
    $player_id = trim($_POST['player_id'] ?? 'player_' . uniqid());
    $room_code = strtoupper(substr(md5(uniqid()), 0, 6));
    
    $game_data = [
        'room_code' => $room_code,
        'status' => 'waiting_for_player_2',
        'player1' => [
            'id' => $player_id,
            'y' => 250,
            'score' => 0,
            'ready' => true,
            'joined_at' => time()
        ],
        'player2' => [
            'id' => '',
            'y' => 250,
            'score' => 0,
            'ready' => false,
            'joined_at' => null
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
    
    $filename = ROOT_DIR . "/data/{$room_code}.json";
    file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'status' => 'success',
        'room_code' => $room_code,
        'player_number' => 1,
        'message' => 'Game created successfully',
        'game_state' => $game_data
    ]);
}

function checkGame() {
    $room_code = trim($_GET['room'] ?? '');
    
    if (empty($room_code)) {
        echo json_encode(['error' => 'Room code required', 'status' => 'error']);
        return;
    }
    
    $filename = ROOT_DIR . "/data/{$room_code}.json";
    
    if (file_exists($filename)) {
        $game_data = json_decode(file_get_contents($filename), true);
        echo json_encode([
            'status' => 'success',
            'exists' => true,
            'room_code' => $room_code,
            'players_connected' => [
                'player1' => !empty($game_data['player1']['id']),
                'player2' => !empty($game_data['player2']['id'])
            ],
            'game_state' => $game_data
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'exists' => false,
            'message' => 'Room not found'
        ]);
    }
}

function leaveGame() {
    $room_code = trim($_POST['room'] ?? '');
    $player_id = trim($_POST['player_id'] ?? '');
    
    if (empty($room_code)) {
        echo json_encode(['error' => 'Room code required', 'status' => 'error']);
        return;
    }
    
    $filename = ROOT_DIR . "/data/{$room_code}.json";
    
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
        
        // Если оба игрока вышли, удаляем файл
        if (empty($game_data['player1']['id']) && empty($game_data['player2']['id'])) {
            unlink($filename);
            echo json_encode(['status' => 'success', 'room_deleted' => true]);
        } else {
            $game_data['game_started'] = false;
            $game_data['status'] = 'waiting';
            file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'success', 'room_deleted' => false]);
        }
    } else {
        echo json_encode(['status' => 'success', 'room_deleted' => true]);
    }
}
?>
