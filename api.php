<?php
session_start();
date_default_timezone_set('Europe/Moscow');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Создаем папку для данных если нет
$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// Обработка OPTIONS запроса
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // Игровые действия
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
        
    // Чат и лобби
    case 'get_chat_messages':
        getChatMessages();
        break;
    case 'send_message':
        sendMessage();
        break;
    case 'get_active_rooms':
        getActiveRooms();
        break;
    case 'update_room_status':
        updateRoomStatus();
        break;
    case 'get_online_count':
        getOnlineCount();
        break;
        
    default:
        echo json_encode([
            'status' => 'ready', 
            'message' => 'Pong Multiplayer API',
            'version' => '2.0',
            'features' => ['game', 'chat', 'lobby', 'stats']
        ]);
}

// ========== ИГРОВЫЕ ФУНКЦИИ ==========

function getGameState() {
    $room_code = trim($_GET['room'] ?? '');
    
    if (empty($room_code)) {
        echo json_encode(['error' => 'Room code required', 'status' => 'error']);
        return;
    }
    
    $filename = $GLOBALS['data_dir'] . "/{$room_code}.json";
    
    if (!file_exists($filename)) {
        // Создаем комнату по умолчанию
        $game_data = [
            'room_code' => $room_code,
            'status' => 'waiting',
            'player1' => ['id' => '', 'name' => '', 'y' => 250, 'score' => 0, 'ready' => false],
            'player2' => ['id' => '', 'name' => '', 'y' => 250, 'score' => 0, 'ready' => false],
            'ball' => ['x' => 400, 'y' => 300, 'dx' => 5, 'dy' => 3],
            'game_started' => false,
            'last_update' => time(),
            'created_at' => time()
        ];
        
        file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
    } else {
        $game_data = json_decode(file_get_contents($filename), true);
        
        // Обновляем активность комнаты
        updateRoomActivity($room_code);
        
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
    
    if ($ball['x'] <= 30 && $ball['x'] >= 20 && 
        $ball['y'] >= $p1_y && $ball['y'] <= $p1_y + 100) {
        $ball['dx'] = abs($ball['dx']);
        $ball['dx'] *= 1.05;
    }
    
    if ($ball['x'] >= 770 && $ball['x'] <= 780 && 
        $ball['y'] >= $p2_y && $ball['y'] <= $p2_y + 100) {
        $ball['dx'] = -abs($ball['dx']);
        $ball['dx'] *= 1.05;
    }
    
    // Гол
    if ($ball['x'] < 0) {
        $game_data['player2']['score']++;
        resetBall($ball);
        if ($game_data['player2']['score'] >= 5) {
            $game_data['status'] = 'finished';
            $game_data['winner'] = 'player2';
            addChatMessage('system', "🎉 Игрок 2 победил со счетом {$game_data['player2']['score']}:{$game_data['player1']['score']}!");
        }
    } elseif ($ball['x'] > 800) {
        $game_data['player1']['score']++;
        resetBall($ball);
        if ($game_data['player1']['score'] >= 5) {
            $game_data['status'] = 'finished';
            $game_data['winner'] = 'player1';
            addChatMessage('system', "🎉 Игрок 1 победил со счетом {$game_data['player1']['score']}:{$game_data['player2']['score']}!");
        }
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
    
    $filename = $GLOBALS['data_dir'] . "/{$room_code}.json";
    
    if (!file_exists($filename)) {
        echo json_encode(['error' => 'Room not found', 'status' => 'error']);
        return;
    }
    
    $game_data = json_decode(file_get_contents($filename), true);
    
    // Обновляем позицию игрока
    $player_key = "player{$player_number}";
    $game_data[$player_key]['y'] = max(0, min(500, $y));
    $game_data[$player_key]['last_move'] = time();
    
    // Обновляем активность комнаты
    updateRoomActivity($room_code, $game_data);
    
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
    $player_name = trim($_POST['player_name'] ?? 'Игрок');
    
    if (empty($room_code) || empty($player_id)) {
        echo json_encode(['error' => 'Room code and player ID required', 'status' => 'error']);
        return;
    }
    
    $filename = $GLOBALS['data_dir'] . "/{$room_code}.json";
    
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
        $game_data['player2']['name'] = $player_name;
        $game_data['player2']['ready'] = true;
        $game_data['status'] = 'playing';
        $game_data['game_started'] = true;
        $game_data['last_update'] = time();
        
        file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
        
        // Обновляем статус комнаты
        updateRoomStatus($room_code, 'playing', 2);
        
        // Добавляем сообщение в чат
        addChatMessage('system', "🎮 Игрок {$player_name} присоединился к комнате {$room_code}!");
        
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
    $player_name = trim($_POST['player_name'] ?? 'Игрок');
    $room_code = strtoupper(substr(md5(uniqid()), 0, 6));
    
    $game_data = [
        'room_code' => $room_code,
        'status' => 'waiting',
        'player1' => [
            'id' => $player_id,
            'name' => $player_name,
            'y' => 250,
            'score' => 0,
            'ready' => true,
            'joined_at' => time()
        ],
        'player2' => [
            'id' => '',
            'name' => '',
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
    
    $filename = $GLOBALS['data_dir'] . "/{$room_code}.json";
    file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
    
    // Добавляем комнату в список
    addRoomToList($room_code, $player_name, $player_id);
    
    // Добавляем сообщение в чат
    addChatMessage('system', "🎯 Игрок {$player_name} создал комнату {$room_code}! Присоединяйтесь!");
    
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
    
    $filename = $GLOBALS['data_dir'] . "/{$room_code}.json";
    
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
    $player_name = trim($_POST['player_name'] ?? 'Игрок');
    
    if (empty($room_code)) {
        echo json_encode(['error' => 'Room code required', 'status' => 'error']);
        return;
    }
    
    $filename = $GLOBALS['data_dir'] . "/{$room_code}.json";
    
    if (file_exists($filename)) {
        $game_data = json_decode(file_get_contents($filename), true);
        
        // Добавляем сообщение о выходе
        if ($game_data['player1']['id'] === $player_id) {
            addChatMessage('system', "👋 Игрок {$player_name} покинул комнату {$room_code}");
        } elseif ($game_data['player2']['id'] === $player_id) {
            addChatMessage('system', "👋 Игрок {$player_name} покинул комнату {$room_code}");
        }
        
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
            removeRoomFromList($room_code);
            echo json_encode(['status' => 'success', 'room_deleted' => true]);
        } else {
            $game_data['game_started'] = false;
            $game_data['status'] = 'waiting';
            file_put_contents($filename, json_encode($game_data, JSON_PRETTY_PRINT));
            
            // Обновляем статус комнаты
            updateRoomStatus($room_code, 'waiting', 1);
            
            echo json_encode(['status' => 'success', 'room_deleted' => false]);
        }
    } else {
        removeRoomFromList($room_code);
        echo json_encode(['status' => 'success', 'room_deleted' => true]);
    }
}

// ========== ЧАТ И ЛОББИ ФУНКЦИИ ==========

function getChatMessages() {
    $chat_file = $GLOBALS['data_dir'] . '/chat.json';
    
    if (!file_exists($chat_file)) {
        echo json_encode(['messages' => []]);
        return;
    }
    
    $chat_data = json_decode(file_get_contents($chat_file), true);
    $messages = $chat_data['messages'] ?? [];
    
    // Возвращаем последние 50 сообщений
    $recent_messages = array_slice(array_reverse($messages), 0, 50);
    
    echo json_encode([
        'status' => 'success',
        'messages' => $recent_messages,
        'count' => count($recent_messages)
    ]);
}

function sendMessage() {
    $player_id = trim($_POST['player_id'] ?? '');
    $player_name = trim($_POST['player_name'] ?? 'Игрок');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($player_id) || empty($message)) {
        echo json_encode(['error' => 'Player ID and message required', 'status' => 'error']);
        return;
    }
    
    addChatMessage($player_id, $message, $player_name);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Message sent'
    ]);
}

function getActiveRooms() {
    $rooms_file = $GLOBALS['data_dir'] . '/rooms.json';
    
    if (!file_exists($rooms_file)) {
        echo json_encode(['rooms' => []]);
        return;
    }
    
    $rooms_data = json_decode(file_get_contents($rooms_file), true);
    $rooms = $rooms_data['rooms'] ?? [];
    
    // Очистка старых комнат
    $current_time = time();
    $cleaned_rooms = [];
    
    foreach ($rooms as $code => $room) {
        if ($current_time - $room['last_activity'] < 1800) { // 30 минут
            $cleaned_rooms[$code] = $room;
        }
    }
    
    // Если были удалены комнаты, сохраняем
    if (count($cleaned_rooms) !== count($rooms)) {
        $rooms_data['rooms'] = $cleaned_rooms;
        $rooms_data['last_cleanup'] = $current_time;
        file_put_contents($rooms_file, json_encode($rooms_data, JSON_PRETTY_PRINT));
    }
    
    echo json_encode([
        'status' => 'success',
        'rooms' => $cleaned_rooms,
        'count' => count($cleaned_rooms)
    ]);
}

function getOnlineCount() {
    $rooms_file = $GLOBALS['data_dir'] . '/rooms.json';
    $online_count = 0;
    
    if (file_exists($rooms_file)) {
        $rooms_data = json_decode(file_get_contents($rooms_file), true);
        $rooms = $rooms_data['rooms'] ?? [];
        
        foreach ($rooms as $room) {
            $online_count += $room['players'];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'online_count' => $online_count
    ]);
}

// ========== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ==========

function addChatMessage($player_id, $message, $player_name = null) {
    $chat_file = $GLOBALS['data_dir'] . '/chat.json';
    
    if (!file_exists($chat_file)) {
        $chat_data = ['messages' => []];
    } else {
        $chat_data = json_decode(file_get_contents($chat_file), true);
    }
    
    $chat_data['messages'][] = [
        'id' => uniqid(),
        'player_id' => $player_id,
        'player_name' => $player_name ?? ($player_id === 'system' ? 'Система' : 'Игрок'),
        'message' => htmlspecialchars($message),
        'time' => time(),
        'timestamp' => date('H:i:s')
    ];
    
    // Ограничиваем до 100 последних сообщений
    if (count($chat_data['messages']) > 100) {
        $chat_data['messages'] = array_slice($chat_data['messages'], -100);
    }
    
    file_put_contents($chat_file, json_encode($chat_data, JSON_PRETTY_PRINT));
}

function addRoomToList($room_code, $creator_name, $creator_id) {
    $rooms_file = $GLOBALS['data_dir'] . '/rooms.json';
    
    if (!file_exists($rooms_file)) {
        $rooms_data = ['rooms' => [], 'last_cleanup' => time()];
    } else {
        $rooms_data = json_decode(file_get_contents($rooms_file), true);
    }
    
    $rooms_data['rooms'][$room_code] = [
        'code' => $room_code,
        'creator' => $creator_name,
        'creator_id' => $creator_id,
        'players' => 1,
        'status' => 'waiting',
        'created_at' => time(),
        'last_activity' => time()
    ];
    
    file_put_contents($rooms_file, json_encode($rooms_data, JSON_PRETTY_PRINT));
}

function updateRoomActivity($room_code, $game_data = null) {
    $rooms_file = $GLOBALS['data_dir'] . '/rooms.json';
    
    if (!file_exists($rooms_file)) return;
    
    $rooms_data = json_decode(file_get_contents($rooms_file), true);
    
    if (isset($rooms_data['rooms'][$room_code])) {
        $rooms_data['rooms'][$room_code]['last_activity'] = time();
        
        if ($game_data) {
            $players_count = 0;
            if (!empty($game_data['player1']['id'])) $players_count++;
            if (!empty($game_data['player2']['id'])) $players_count++;
            
            $rooms_data['rooms'][$room_code]['players'] = $players_count;
            $rooms_data['rooms'][$room_code]['status'] = $game_data['game_started'] ? 'playing' : 'waiting';
        }
        
        file_put_contents($rooms_file, json_encode($rooms_data, JSON_PRETTY_PRINT));
    }
}

function updateRoomStatus($room_code, $status, $players_count) {
    $rooms_file = $GLOBALS['data_dir'] . '/rooms.json';
    
    if (!file_exists($rooms_file)) return;
    
    $rooms_data = json_decode(file_get_contents($rooms_file), true);
    
    if (isset($rooms_data['rooms'][$room_code])) {
        $rooms_data['rooms'][$room_code]['status'] = $status;
        $rooms_data['rooms'][$room_code]['players'] = $players_count;
        $rooms_data['rooms'][$room_code]['last_activity'] = time();
        
        file_put_contents($rooms_file, json_encode($rooms_data, JSON_PRETTY_PRINT));
    }
}

function removeRoomFromList($room_code) {
    $rooms_file = $GLOBALS['data_dir'] . '/rooms.json';
    
    if (!file_exists($rooms_file)) return;
    
    $rooms_data = json_decode(file_get_contents($rooms_file), true);
    
    if (isset($rooms_data['rooms'][$room_code])) {
        unset($rooms_data['rooms'][$room_code]);
        file_put_contents($rooms_file, json_encode($rooms_data, JSON_PRETTY_PRINT));
    }
}

// Автоматическая очистка старых файлов при каждом запросе
function cleanupOldFiles() {
    $data_dir = $GLOBALS['data_dir'];
    $current_time = time();
    
    // Очистка старых игровых файлов (старше 2 часов)
    $files = glob($data_dir . '/*.json');
    foreach ($files as $file) {
        $filename = basename($file);
        if ($filename !== 'chat.json' && $filename !== 'rooms.json') {
            if ($current_time - filemtime($file) > 7200) { // 2 часа
                unlink($file);
            }
        }
    }
}

// Вызываем очистку
cleanupOldFiles();
?>
