<?php
session_start();

// Генерируем уникальный ID для игрока
if (!isset($_SESSION['player_id'])) {
    $_SESSION['player_id'] = uniqid('player_', true);
}

// Генерируем уникальную комнату или код для подключения
$room_code = isset($_GET['room']) ? $_GET['room'] : '';

if (empty($room_code) && isset($_POST['create_game'])) {
    $room_code = strtoupper(substr(md5(uniqid()), 0, 6));
    header("Location: game.php?room=$room_code&player=1");
    exit;
}

if (!empty($room_code) && isset($_POST['join_game'])) {
    header("Location: game.php?room=$room_code&player=2");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pong - Мультиплеер</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="screen home-screen">
            <h1>🎮 PONG MULTIPLAYER</h1>
            <p class="subtitle">Игра для двух игроков на телефонах</p>
            
            <div class="card">
                <h2>Создать игру</h2>
                <p>Создайте комнату и поделитесь кодом с другом</p>
                <form method="POST">
                    <button type="submit" name="create_game" class="btn btn-primary">
                        Создать новую игру
                    </button>
                </form>
            </div>
            
            <div class="card">
                <h2>Присоединиться</h2>
                <p>Введите код комнаты от друга</p>
                <form method="POST">
                    <div class="input-group">
                        <input type="text" name="room" placeholder="Введите код (6 символов)" 
                               pattern="[A-Z0-9]{6}" maxlength="6" required
                               class="room-input">
                        <button type="submit" name="join_game" class="btn btn-secondary">
                            Присоединиться
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="instructions">
                <h3>Как играть:</h3>
                <ol>
                    <li>Создатель игры получает код комнаты</li>
                    <li>Делится кодом с другом</li>
                    <li>Друг вводит код на своём телефоне</li>
                    <li>Начинайте играть!</li>
                </ol>
                <p class="note">Игра автоматически синхронизируется через сервер</p>
            </div>
        </div>
    </div>
</body>
</html>