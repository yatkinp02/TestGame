<?php
session_start();
date_default_timezone_set('Europe/Moscow');

// Генерируем уникальный ID для игрока
if (!isset($_SESSION['player_id'])) {
    $_SESSION['player_id'] = 'player_' . uniqid() . '_' . rand(1000, 9999);
}

// Генерируем имя игрока если нет
if (!isset($_SESSION['player_name'])) {
    $names = ['Игрок', 'Чемпион', 'Ниндзя', 'Мастер', 'Профи', 'Гуру', 'Легенда'];
    $_SESSION['player_name'] = $names[array_rand($names)] . ' ' . rand(100, 999);
}

$player_name = $_SESSION['player_name'];
$player_id = $_SESSION['player_id'];

// Создаем папку для данных если нет
$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
    file_put_contents($data_dir . '/.htaccess', "Deny from all\n");
    file_put_contents($data_dir . '/chat.json', json_encode(['messages' => []]));
    file_put_contents($data_dir . '/rooms.json', json_encode(['rooms' => [], 'last_cleanup' => time()]));
}

// Обработка отправки сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message']) && !empty(trim($_POST['message']))) {
        $message = [
            'id' => uniqid(),
            'player_id' => $player_id,
            'player_name' => $player_name,
            'message' => trim(htmlspecialchars($_POST['message'])),
            'time' => time(),
            'timestamp' => date('H:i:s')
        ];
        
        $chat_file = $data_dir . '/chat.json';
        if (file_exists($chat_file)) {
            $chat_data = json_decode(file_get_contents($chat_file), true);
            $chat_data['messages'][] = $message;
            
            // Ограничиваем до 50 последних сообщений
            if (count($chat_data['messages']) > 50) {
                $chat_data['messages'] = array_slice($chat_data['messages'], -50);
            }
            
            file_put_contents($chat_file, json_encode($chat_data, JSON_PRETTY_PRINT));
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Создание игры
    if (isset($_POST['create_game'])) {
        $room_code = strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Сохраняем комнату в список
        $rooms_file = $data_dir . '/rooms.json';
        if (file_exists($rooms_file)) {
            $rooms_data = json_decode(file_get_contents($rooms_file), true);
            $rooms_data['rooms'][$room_code] = [
                'code' => $room_code,
                'creator' => $player_name,
                'creator_id' => $player_id,
                'players' => 1,
                'status' => 'waiting',
                'created_at' => time(),
                'last_activity' => time()
            ];
            file_put_contents($rooms_file, json_encode($rooms_data, JSON_PRETTY_PRINT));
        }
        
        header("Location: game.php?room={$room_code}&player=1");
        exit;
    }
    
    // Присоединение к игре
    if (isset($_POST['join_game']) && !empty($_POST['room'])) {
        $room_code = strtoupper(trim($_POST['room']));
        header("Location: game.php?room={$room_code}&player=2");
        exit;
    }
}

// Получаем последние сообщения
$chat_messages = [];
$chat_file = $data_dir . '/chat.json';
if (file_exists($chat_file)) {
    $chat_data = json_decode(file_get_contents($chat_file), true);
    $chat_messages = array_reverse($chat_data['messages'] ?? []);
}

// Получаем активные лобби
$active_rooms = [];
$rooms_file = $data_dir . '/rooms.json';
if (file_exists($rooms_file)) {
    $rooms_data = json_decode(file_get_contents($rooms_file), true);
    $rooms = $rooms_data['rooms'] ?? [];
    
    // Фильтруем активные комнаты (не старше 30 минут)
    $current_time = time();
    foreach ($rooms as $code => $room) {
        if ($current_time - $room['last_activity'] < 1800) { // 30 минут
            $active_rooms[$code] = $room;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pong Multiplayer - Главное меню</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #4CAF50;
            --info: #2196F3;
            --warning: #FF9800;
            --danger: #F44336;
            --dark: #2D3748;
            --light: #F7FAFC;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* Хедер */
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo h1 {
            font-size: 2em;
            margin: 0;
            background: linear-gradient(45deg, #fff, #FFEB3B);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 50px;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--info);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Карточки */
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .card h2 {
            margin-bottom: 20px;
            color: white;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2 i {
            font-size: 1.2em;
        }
        
        /* Игровые карточки */
        .game-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .game-actions {
                grid-template-columns: 1fr;
            }
        }
        
        .create-game, .join-game {
            padding: 25px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 25px;
            border: none;
            border-radius: 12px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            width: 100%;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--success), #2E7D32);
        }
        
        .btn-secondary {
            background: linear-gradient(45deg, var(--info), #0D47A1);
            margin-top: 15px;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        /* Формы */
        .input-group {
            margin-bottom: 15px;
        }
        
        .room-input {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1.2em;
            text-align: center;
            letter-spacing: 3px;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            margin-bottom: 10px;
        }
        
        /* Лобби */
        .lobby-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .lobby-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        
        .lobby-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .lobby-code {
            font-family: monospace;
            font-size: 1.3em;
            font-weight: bold;
            color: #FFEB3B;
            letter-spacing: 2px;
        }
        
        .lobby-info {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        .lobby-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-waiting {
            background: rgba(255, 193, 7, 0.2);
            color: #FFC107;
        }
        
        .status-playing {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        
        .join-lobby-btn {
            padding: 8px 15px;
            background: var(--info);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .join-lobby-btn:hover {
            background: #1976D2;
        }
        
        /* Чат */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 400px;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column-reverse;
        }
        
        .message {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 15px;
            border-radius: 15px;
            margin-bottom: 10px;
            max-width: 85%;
            word-wrap: break-word;
        }
        
        .message.own {
            background: rgba(33, 150, 243, 0.3);
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }
        
        .message.other {
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85em;
            opacity: 0.8;
        }
        
        .player-name {
            font-weight: bold;
            color: #FFEB3B;
        }
        
        .message-time {
            opacity: 0.7;
        }
        
        .message-text {
            line-height: 1.4;
        }
        
        .chat-input {
            display: flex;
            gap: 10px;
        }
        
        .chat-input input {
            flex: 1;
            padding: 12px 15px;
            border: none;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            font-size: 1em;
        }
        
        .chat-input button {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            background: var(--info);
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .chat-input button:hover {
            background: #1976D2;
        }
        
        /* Статистика */
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            text-align: center;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 10px;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #FFEB3B;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        /* Инструкции */
        .instructions {
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        .instructions h3 {
            margin-bottom: 15px;
            color: #FFEB3B;
        }
        
        .instructions ol {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
        
        /* Иконки */
        .icon {
            font-size: 1.2em;
        }
        
        /* Специальные классы */
        .no-rooms {
            text-align: center;
            padding: 30px;
            opacity: 0.7;
        }
        
        .no-messages {
            text-align: center;
            padding: 30px;
            opacity: 0.7;
        }
        
        .refresh-btn {
            margin-top: 10px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }
        
        .refresh-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Анимации */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Основной контент -->
        <div class="main-content">
            <!-- Хедер -->
            <div class="header card">
                <div class="logo">
                    <div style="font-size: 2em;">🎮</div>
                    <h1>PONG MULTIPLAYER</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?php echo substr($player_name, 0, 1); ?></div>
                    <span><?php echo $player_name; ?></span>
                </div>
            </div>
            
            <!-- Игровые действия -->
            <div class="game-actions">
                <div class="card create-game">
                    <h2><span class="icon">🎯</span> Создать игру</h2>
                    <p>Создайте новую комнату и пригласите друга</p>
                    <form method="POST">
                        <button type="submit" name="create_game" class="btn btn-primary">
                            <span class="icon">➕</span> Создать комнату
                        </button>
                    </form>
                </div>
                
                <div class="card join-game">
                    <h2><span class="icon">🎮</span> Присоединиться</h2>
                    <p>Введите код комнаты или выберите из списка</p>
                    <form method="POST">
                        <div class="input-group">
                            <input type="text" name="room" 
                                   placeholder="Введите 6-значный код" 
                                   pattern="[A-Z0-9]{6}" 
                                   maxlength="6" 
                                   required
                                   class="room-input">
                        </div>
                        <button type="submit" name="join_game" class="btn btn-secondary">
                            <span class="icon">🚀</span> Присоединиться
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Активные лобби -->
            <div class="card">
                <h2><span class="icon">👥</span> Активные лобби</h2>
                <div class="lobby-list" id="lobbyList">
                    <?php if (!empty($active_rooms)): ?>
                        <?php foreach ($active_rooms as $room): ?>
                            <div class="lobby-item fade-in">
                                <div>
                                    <div class="lobby-code"><?php echo $room['code']; ?></div>
                                    <div class="lobby-info">
                                        Создатель: <?php echo $room['creator']; ?> • 
                                        Игроков: <?php echo $room['players']; ?>/2
                                    </div>
                                    <span class="lobby-status status-<?php echo $room['status']; ?>">
                                        <?php echo $room['status'] === 'waiting' ? 'Ожидание' : 'Игра'; ?>
                                    </span>
                                </div>
                                <button class="join-lobby-btn" 
                                        onclick="joinRoom('<?php echo $room['code']; ?>')">
                                    Присоединиться
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-rooms">
                            <p style="font-size: 2em;">👀</p>
                            <p>Нет активных игр</p>
                            <p>Создайте первую!</p>
                        </div>
                    <?php endif; ?>
                </div>
                <button class="refresh-btn" onclick="refreshLobby()">
                    <span class="icon">🔄</span> Обновить список
                </button>
            </div>
            
            <!-- Инструкции -->
            <div class="card instructions">
                <h3>📖 Как играть:</h3>
                <ol>
                    <li><strong>Создайте игру</strong> или <strong>присоединитесь</strong> к существующей</li>
                    <li>Используйте <strong>кнопки ↑ и ↓</strong> для управления ракеткой</li>
                    <li>Отбивайте мяч и забивайте голы противнику</li>
                    <li>Играйте до 5 очков</li>
                    <li>Общайтесь в чате с другими игроками</li>
                </ol>
            </div>
        </div>
        
        <!-- Сайдбар -->
        <div class="sidebar">
            <!-- Статистика -->
            <div class="card">
                <h2><span class="icon">📊</span> Статистика</h2>
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-label">Активных игр</div>
                        <div class="stat-value"><?php echo count($active_rooms); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Сообщений</div>
                        <div class="stat-value"><?php echo count($chat_messages); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Чат -->
            <div class="card">
                <h2><span class="icon">💬</span> Общий чат</h2>
                <div class="chat-container">
                    <div class="chat-messages" id="chatMessages">
                        <?php if (!empty($chat_messages)): ?>
                            <?php foreach ($chat_messages as $message): ?>
                                <div class="message <?php echo $message['player_id'] === $player_id ? 'own' : 'other'; ?>">
                                    <div class="message-header">
                                        <span class="player-name"><?php echo $message['player_name']; ?></span>
                                        <span class="message-time"><?php echo $message['timestamp']; ?></span>
                                    </div>
                                    <div class="message-text"><?php echo $message['message']; ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-messages">
                                <p>Пока нет сообщений</p>
                                <p>Будьте первым!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" class="chat-input">
                        <input type="text" name="message" 
                               placeholder="Введите сообщение..." 
                               maxlength="200"
                               required>
                        <button type="submit">
                            <span class="icon">📨</span>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Онлайн игроки -->
            <div class="card">
                <h2><span class="icon">👤</span> Вы онлайн</h2>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 3em; margin-bottom: 10px;">👋</div>
                    <h3><?php echo $player_name; ?></h3>
                    <p style="opacity: 0.8; font-size: 0.9em;">Готов к игре!</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Функция для присоединения к комнате
        function joinRoom(roomCode) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'room';
            input.value = roomCode;
            
            const submit = document.createElement('input');
            submit.type = 'hidden';
            submit.name = 'join_game';
            submit.value = '1';
            
            form.appendChild(input);
            form.appendChild(submit);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Функция обновления лобби
        function refreshLobby() {
            location.reload();
        }
        
        // Автоскролл чата вниз
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Обновление каждые 30 секунд
        setInterval(() => {
            // Обновляем только список лобби
            fetch('api.php?action=get_active_rooms')
                .then(response => response.json())
                .then(data => {
                    if (data.rooms) {
                        updateLobbyList(data.rooms);
                    }
                });
        }, 30000);
        
        // Обновление чата каждые 10 секунд
        setInterval(() => {
            fetch('api.php?action=get_chat_messages')
                .then(response => response.json())
                .then(data => {
                    if (data.messages) {
                        updateChat(data.messages);
                    }
                });
        }, 10000);
        
        function updateLobbyList(rooms) {
            const lobbyList = document.getElementById('lobbyList');
            if (!rooms || Object.keys(rooms).length === 0) {
                lobbyList.innerHTML = `
                    <div class="no-rooms">
                        <p style="font-size: 2em;">👀</p>
                        <p>Нет активных игр</p>
                        <p>Создайте первую!</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            Object.values(rooms).forEach(room => {
                html += `
                    <div class="lobby-item fade-in">
                        <div>
                            <div class="lobby-code">${room.code}</div>
                            <div class="lobby-info">
                                Создатель: ${room.creator} • 
                                Игроков: ${room.players}/2
                            </div>
                            <span class="lobby-status status-${room.status}">
                                ${room.status === 'waiting' ? 'Ожидание' : 'Игра'}
                            </span>
                        </div>
                        <button class="join-lobby-btn" 
                                onclick="joinRoom('${room.code}')">
                            Присоединиться
                        </button>
                    </div>
                `;
            });
            
            lobbyList.innerHTML = html;
        }
        
        function updateChat(messages) {
            const chatMessages = document.getElementById('chatMessages');
            const playerId = '<?php echo $player_id; ?>';
            
            if (!messages || messages.length === 0) {
                chatMessages.innerHTML = `
                    <div class="no-messages">
                        <p>Пока нет сообщений</p>
                        <p>Будьте первым!</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            messages.forEach(message => {
                const isOwn = message.player_id === playerId;
                html += `
                    <div class="message ${isOwn ? 'own' : 'other'}">
                        <div class="message-header">
                            <span class="player-name">${message.player_name}</span>
                            <span class="message-time">${message.timestamp}</span>
                        </div>
                        <div class="message-text">${message.message}</div>
                    </div>
                `;
            });
            
            chatMessages.innerHTML = html;
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Обработка горячих клавиш
        document.addEventListener('keydown', (e) => {
            // Ctrl+Enter для отправки сообщения
            if (e.ctrlKey && e.key === 'Enter') {
                const chatInput = document.querySelector('.chat-input input');
                if (chatInput && document.activeElement === chatInput) {
                    chatInput.form.submit();
                }
            }
            
            // F5 для обновления лобби
            if (e.key === 'F5') {
                e.preventDefault();
                refreshLobby();
            }
        });
    </script>
</body>
</html>
