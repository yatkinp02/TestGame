<?php
session_start();

$room_code = isset($_GET['room']) ? $_GET['room'] : '';
$player_number = isset($_GET['player']) ? intval($_GET['player']) : 1;

if (empty($room_code)) {
    header("Location: index.php");
    exit;
}

// Сохраняем данные в сессии
$_SESSION['room_code'] = $room_code;
$_SESSION['player_number'] = $player_number;

// Если игрок 1, создаем файл комнаты
if ($player_number == 1) {
    $game_data = [
        'room_code' => $room_code,
        'player1' => [
            'id' => $_SESSION['player_id'],
            'ready' => false,
            'y' => 250,
            'score' => 0
        ],
        'player2' => [
            'id' => '',
            'ready' => false,
            'y' => 250,
            'score' => 0
        ],
        'ball' => [
            'x' => 400,
            'y' => 300,
            'dx' => 5,
            'dy' => 3
        ],
        'game_started' => false,
        'created_at' => time()
    ];
    
    // Сохраняем в файл (в реальном проекте лучше использовать базу данных)
    file_put_contents("rooms/$room_code.json", json_encode($game_data));
}

// Создаем папку для комнат если её нет
if (!is_dir('rooms')) {
    mkdir('rooms', 0777, true);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pong - Игра <?php echo $room_code; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        canvas {
            display: block;
            background: #000;
            border-radius: 10px;
            max-width: 100%;
            height: auto;
            touch-action: none;
        }
        
        .game-area {
            position: relative;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .game-info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .player-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 0 5px;
        }
        
        .player-1 { background: #4CAF50; }
        .player-2 { background: #2196F3; }
        
        .score {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .control-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }
        
        .control-btn {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: none;
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 2em;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .control-btn:active {
            background: rgba(255,255,255,0.4);
            transform: scale(0.95);
        }
        
        #waitingScreen {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.9);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            z-index: 100;
            width: 90%;
            max-width: 400px;
        }
        
        .room-code {
            font-size: 2.5em;
            font-weight: bold;
            letter-spacing: 5px;
            color: #FFEB3B;
            margin: 20px 0;
            font-family: monospace;
        }
        
        .share-link {
            background: #1976D2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 1em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="screen game-screen">
            <div class="game-info">
                <div>
                    Вы: <span class="player-badge player-<?php echo $player_number; ?>">
                        Игрок <?php echo $player_number; ?>
                    </span>
                </div>
                <div>Комната: <strong><?php echo $room_code; ?></strong></div>
                <div class="score">
                    <span id="score1">0</span> : <span id="score2">0</span>
                </div>
            </div>
            
            <div class="game-area">
                <canvas id="gameCanvas" width="800" height="600"></canvas>
                
                <?php if ($player_number == 1): ?>
                <div id="waitingScreen">
                    <h2>Ожидание игрока 2</h2>
                    <p>Поделитесь кодом комнаты с другом:</p>
                    <div class="room-code"><?php echo $room_code; ?></div>
                    <p>Или отправьте ссылку:</p>
                    <button class="share-link" onclick="shareGame()">
                        📤 Поделиться ссылкой
                    </button>
                    <p id="statusMessage">Ожидание подключения...</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="control-buttons">
                <button class="control-btn" id="upBtn">↑</button>
                <button class="control-btn" id="downBtn">↓</button>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button id="leaveBtn" class="btn btn-danger">Выйти из игры</button>
            </div>
        </div>
    </div>
    
    <script>
        // Конфигурация игры
        const CONFIG = {
            roomCode: "<?php echo $room_code; ?>",
            playerNumber: <?php echo $player_number; ?>,
            playerId: "<?php echo $_SESSION['player_id'] ?? 'player_' . uniqid(); ?>",
            apiUrl: "api.php"
        };
        
        console.log('Game config:', CONFIG);
    </script>
    <script src="game.js"></script>
    <script>
        // Функция для шаринга ссылки
        function shareGame() {
            const gameUrl = window.location.origin + window.location.pathname + 
                          '?room=<?php echo $room_code; ?>&player=2';
            
            if (navigator.share) {
                navigator.share({
                    title: 'Присоединяйся к Pong Multiplayer!',
                    text: 'Сыграем в Pong? Код комнаты: <?php echo $room_code; ?>',
                    url: gameUrl
                });
            } else {
                // Копируем в буфер обмена
                navigator.clipboard.writeText(gameUrl).then(() => {
                    alert('Ссылка скопирована! Отправь её другу.\n' + gameUrl);
                });
            }
        }
        
        // Кнопка выхода
        document.getElementById('leaveBtn').addEventListener('click', () => {
            if (confirm('Выйти из игры?')) {
                window.location.href = 'index.php';
            }
        });
    </script>
</body>
</html>
