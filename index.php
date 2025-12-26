<?php
session_start();

// Генерируем уникальный ID для игрока
if (!isset($_SESSION['player_id'])) {
    $_SESSION['player_id'] = 'player_' . uniqid() . '_' . rand(1000, 9999);
}

// Обработка создания игры
if (isset($_POST['create_game'])) {
    // Отправляем AJAX запрос для создания игры
    $player_id = $_SESSION['player_id'];
    
    // В реальном коде здесь будет fetch запрос
    // Для простоты перенаправляем с параметром
    $room_code = strtoupper(substr(md5(uniqid()), 0, 6));
    header("Location: game.php?room={$room_code}&player=1&create=1");
    exit;
}

// Обработка присоединения
if (isset($_POST['join_game']) && !empty($_POST['room'])) {
    $room_code = strtoupper(trim($_POST['room']));
    header("Location: game.php?room={$room_code}&player=2");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pong Multiplayer</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Arial', sans-serif;
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }
        
        .container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }
        
        .logo h1 {
            font-size: 2.5em;
            margin: 10px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .logo .subtitle {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .card h2 {
            color: white;
            margin-top: 0;
            text-align: center;
        }
        
        .card p {
            color: rgba(255,255,255,0.9);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 50px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #4CAF50, #2E7D32);
        }
        
        .btn-secondary {
            background: linear-gradient(45deg, #2196F3, #0D47A1);
            margin-top: 10px;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
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
            background: rgba(255,255,255,0.9);
            margin-bottom: 10px;
        }
        
        .instructions {
            color: white;
            margin-top: 30px;
            font-size: 0.9em;
            text-align: center;
            opacity: 0.8;
        }
        
        .status {
            padding: 10px;
            border-radius: 10px;
            margin: 10px 0;
            text-align: center;
            font-weight: bold;
            display: none;
        }
        
        .status.success {
            background: rgba(76, 175, 80, 0.3);
            color: #C8E6C9;
            display: block;
        }
        
        .status.error {
            background: rgba(244, 67, 54, 0.3);
            color: #FFCDD2;
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🎮 PONG</h1>
            <div class="subtitle">Игра для двух игроков</div>
        </div>
        
        <div class="card">
            <h2>Создать игру</h2>
            <p>Создайте комнату и пригласите друга</p>
            <form method="POST" id="createForm">
                <button type="submit" name="create_game" class="btn btn-primary">
                    🎯 Создать новую игру
                </button>
            </form>
        </div>
        
        <div class="card">
            <h2>Присоединиться</h2>
            <p>Введите код комнаты от друга</p>
            <form method="POST" id="joinForm">
                <div class="input-group">
                    <input type="text" name="room" 
                           placeholder="Например: 3B0043" 
                           pattern="[A-Z0-9]{6}" 
                           maxlength="6" 
                           required
                           class="room-input">
                </div>
                <button type="submit" name="join_game" class="btn btn-secondary">
                    🎮 Присоединиться к игре
                </button>
            </form>
        </div>
        
        <div class="instructions">
            <p><strong>Как играть:</strong></p>
            <p>1. Создатель игры получает 6-значный код</p>
            <p>2. Делится кодом с другом</p>
            <p>3. Друг вводит код и присоединяется</p>
            <p>4. Используйте кнопки ↑ и ↓ для управления</p>
        </div>
        
        <div id="statusMessage" class="status"></div>
    </div>
    
    <script>
        // Обработка форм с AJAX для лучшего UX
        document.getElementById('createForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const statusEl = document.getElementById('statusMessage');
            statusEl.textContent = 'Создание игры...';
            statusEl.className = 'status';
            
            try {
                const formData = new FormData();
                formData.append('player_id', '<?php echo $_SESSION['player_id']; ?>');
                
                const response = await fetch('api.php?action=create_game', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    statusEl.textContent = `Игра создана! Код: ${data.room_code}`;
                    statusEl.className = 'status success';
                    
                    // Перенаправляем в игру через 1.5 секунды
                    setTimeout(() => {
                        window.location.href = `game.php?room=${data.room_code}&player=1`;
                    }, 1500);
                } else {
                    statusEl.textContent = 'Ошибка: ' + (data.message || data.error);
                    statusEl.className = 'status error';
                }
            } catch (error) {
                statusEl.textContent = 'Ошибка сети: ' + error.message;
                statusEl.className = 'status error';
            }
        });
        
        document.getElementById('joinForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const roomCode = document.querySelector('[name="room"]').value.toUpperCase();
            const statusEl = document.getElementById('statusMessage');
            statusEl.textContent = 'Проверка комнаты...';
            statusEl.className = 'status';
            
            try {
                // Сначала проверяем существует ли комната
                const checkResponse = await fetch(`api.php?action=check_game&room=${roomCode}`);
                const checkData = await checkResponse.json();
                
                if (!checkData.exists) {
                    statusEl.textContent = 'Комната не найдена!';
                    statusEl.className = 'status error';
                    return;
                }
                
                // Затем присоединяемся
                const formData = new FormData();
                formData.append('room', roomCode);
                formData.append('player_id', '<?php echo $_SESSION['player_id']; ?>');
                
                const joinResponse = await fetch('api.php?action=join_game', {
                    method: 'POST',
                    body: formData
                });
                
                const joinData = await joinResponse.json();
                
                if (joinData.status === 'success') {
                    statusEl.textContent = `Присоединяемся к игре...`;
                    statusEl.className = 'status success';
                    
                    // Перенаправляем в игру
                    setTimeout(() => {
                        window.location.href = `game.php?room=${roomCode}&player=${joinData.player_number}`;
                    }, 1000);
                } else {
                    statusEl.textContent = 'Ошибка: ' + (joinData.message || joinData.error);
                    statusEl.className = 'status error';
                }
            } catch (error) {
                statusEl.textContent = 'Ошибка сети: ' + error.message;
                statusEl.className = 'status error';
            }
        });
    </script>
</body>
</html>
