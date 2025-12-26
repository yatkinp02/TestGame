class PongGame {
    constructor(config) {
        this.config = config;
        this.canvas = document.getElementById('gameCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.gameState = null;
        this.isPlayer1 = config.playerNumber === 1;
        this.playerId = this.getPlayerId();
        this.isConnected = false;
        this.gameLoopInterval = null;
        this.updateInterval = null;
        this.moveDirection = 0;
        this.moveInterval = null;
        
        console.log('Game initialized:', {
            room: config.roomCode,
            playerNumber: config.playerNumber,
            playerId: this.playerId
        });
        
        this.init();
    }
    
    getPlayerId() {
        // Генерируем уникальный ID для игрока
        let playerId = localStorage.getItem('pong_player_id');
        if (!playerId) {
            playerId = 'player_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('pong_player_id', playerId);
        }
        return playerId;
    }
    
    init() {
        this.setupCanvas();
        this.setupControls();
        this.connectToGame();
        this.startGameLoop();
    }
    
    setupCanvas() {
        // Адаптивный размер канваса
        const resize = () => {
            const container = this.canvas.parentElement;
            const maxWidth = Math.min(800, container.clientWidth - 40);
            const aspectRatio = 800 / 600;
            const height = maxWidth / aspectRatio;
            
            this.canvas.style.width = maxWidth + 'px';
            this.canvas.style.height = height + 'px';
            
            // Сохраняем масштаб для правильного отображения
            this.scaleX = maxWidth / 800;
            this.scaleY = height / 600;
        };
        
        resize();
        window.addEventListener('resize', resize);
    }
    
    setupControls() {
        // Кнопки управления
        const upBtn = document.getElementById('upBtn');
        const downBtn = document.getElementById('downBtn');
        
        if (upBtn && downBtn) {
            // Touch события для мобильных
            upBtn.addEventListener('touchstart', (e) => {
                e.preventDefault();
                this.moveDirection = -1;
                this.startMoving();
            });
            
            upBtn.addEventListener('touchend', (e) => {
                e.preventDefault();
                this.moveDirection = 0;
                this.stopMoving();
            });
            
            downBtn.addEventListener('touchstart', (e) => {
                e.preventDefault();
                this.moveDirection = 1;
                this.startMoving();
            });
            
            downBtn.addEventListener('touchend', (e) => {
                e.preventDefault();
                this.moveDirection = 0;
                this.stopMoving();
            });
            
            // Клики для десктопа
            upBtn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                this.moveDirection = -1;
                this.startMoving();
            });
            
            upBtn.addEventListener('mouseup', (e) => {
                e.preventDefault();
                this.moveDirection = 0;
                this.stopMoving();
            });
            
            downBtn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                this.moveDirection = 1;
                this.startMoving();
            });
            
            downBtn.addEventListener('mouseup', (e) => {
                e.preventDefault();
                this.moveDirection = 0;
                this.stopMoving();
            });
            
            // Отпускание при уходе с кнопки
            upBtn.addEventListener('mouseleave', () => {
                this.moveDirection = 0;
                this.stopMoving();
            });
            
            downBtn.addEventListener('mouseleave', () => {
                this.moveDirection = 0;
                this.stopMoving();
            });
        }
        
        // Клавиатура для тестирования
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowUp' || e.key === 'w' || e.key === 'ц') {
                this.moveDirection = -1;
                this.startMoving();
            }
            if (e.key === 'ArrowDown' || e.key === 's' || e.key === 'ы') {
                this.moveDirection = 1;
                this.startMoving();
            }
        });
        
        document.addEventListener('keyup', (e) => {
            if (e.key === 'ArrowUp' || e.key === 'w' || e.key === 'ц' || 
                e.key === 'ArrowDown' || e.key === 's' || e.key === 'ы') {
                this.moveDirection = 0;
                this.stopMoving();
            }
        });
    }
    
    startMoving() {
        if (this.moveInterval) clearInterval(this.moveInterval);
        
        this.moveInterval = setInterval(() => {
            if (this.moveDirection !== 0 && this.gameState) {
                const playerKey = `player${this.config.playerNumber}`;
                const currentY = this.gameState[playerKey]?.y || 250;
                const newY = currentY + (this.moveDirection * 10);
                
                // Ограничиваем движение
                if (newY >= 0 && newY <= 500) {
                    this.sendPlayerPosition(newY);
                }
            }
        }, 50);
    }
    
    stopMoving() {
        if (this.moveInterval) {
            clearInterval(this.moveInterval);
            this.moveInterval = null;
        }
    }
    
    async connectToGame() {
        console.log('Connecting to game...', {
            room: this.config.roomCode,
            playerId: this.playerId,
            playerNumber: this.config.playerNumber
        });
        
        try {
            const formData = new FormData();
            formData.append('room', this.config.roomCode);
            formData.append('player_id', this.playerId);
            
            console.log('Sending join request:', {
                room: this.config.roomCode,
                player_id: this.playerId
            });
            
            const response = await fetch('api.php?action=join_game', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            console.log('Join response:', data);
            
            if (data.status === 'success') {
                this.isConnected = true;
                this.gameState = data.game_state || data;
                
                // Если это игрок 1 и второй игрок уже подключен
                if (this.config.playerNumber === 1 && 
                    this.gameState.player2 && 
                    this.gameState.player2.id) {
                    this.hideWaitingScreen();
                }
                
                // Если это игрок 2
                if (this.config.playerNumber === 2) {
                    this.hideWaitingScreen();
                }
                
                console.log('Successfully connected as player', data.player_number || this.config.playerNumber);
                
                // Обновляем интерфейс
                this.updateUI();
            } else {
                console.error('Connection error:', data.error || data.message);
                
                // Показываем ошибку пользователю
                const statusMessage = document.getElementById('statusMessage');
                if (statusMessage) {
                    statusMessage.textContent = data.error || data.message || 'Ошибка подключения';
                    statusMessage.style.color = '#ff4444';
                }
                
                // Если комната не существует, создаем ее (для игрока 1)
                if (this.isPlayer1 && (data.error === 'Room not found' || data.status === 'error')) {
                    await this.createRoom();
                }
            }
        } catch (error) {
            console.error('Network error:', error);
            
            const statusMessage = document.getElementById('statusMessage');
            if (statusMessage) {
                statusMessage.textContent = 'Ошибка сети. Проверьте подключение.';
                statusMessage.style.color = '#ff4444';
            }
        }
    }
    
    async createRoom() {
        console.log('Creating room...');
        
        try {
            const formData = new FormData();
            formData.append('player_id', this.playerId);
            
            const response = await fetch('api.php?action=create_game', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            console.log('Create room response:', data);
            
            if (data.status === 'success') {
                this.isConnected = true;
                this.gameState = data.game_state;
                console.log('Room created successfully');
                
                // Обновляем URL с новым кодом комнаты если нужно
                if (data.room_code !== this.config.roomCode) {
                    window.history.replaceState({}, '', `game.php?room=${data.room_code}&player=1`);
                    this.config.roomCode = data.room_code;
                }
            }
        } catch (error) {
            console.error('Error creating room:', error);
        }
    }
    
    async updateGameState() {
        if (!this.isConnected) return;
        
        try {
            const response = await fetch(`api.php?action=get_state&room=${this.config.roomCode}&_=${Date.now()}`);
            const data = await response.json();
            
            if (data.error) {
                console.error('Error getting game state:', data.error);
                return;
            }
            
            this.gameState = data;
            
            // Обновляем счет
            if (data.player1 && data.player2) {
                const score1Elem = document.getElementById('score1');
                const score2Elem = document.getElementById('score2');
                
                if (score1Elem) score1Elem.textContent = data.player1.score || 0;
                if (score2Elem) score2Elem.textContent = data.player2.score || 0;
            }
            
            // Проверяем подключение второго игрока
            if (this.isPlayer1 && data.player2 && data.player2.id) {
                this.hideWaitingScreen();
            }
            
        } catch (error) {
            console.error('Error updating game state:', error);
        }
    }
    
    async sendPlayerPosition(y) {
        if (!this.isConnected || !this.gameState) return;
        
        try {
            const formData = new FormData();
            formData.append('room', this.config.roomCode);
            formData.append('player_id', this.playerId);
            formData.append('player_number', this.config.playerNumber);
            formData.append('y', y);
            
            await fetch('api.php?action=update_player', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Error sending player position:', error);
        }
    }
    
    hideWaitingScreen() {
        const waitingScreen = document.getElementById('waitingScreen');
        if (waitingScreen) {
            waitingScreen.style.display = 'none';
        }
        
        const canvas = document.getElementById('gameCanvas');
        if (canvas) {
            canvas.style.display = 'block';
        }
    }
    
    updateUI() {
        // Обновляем информацию об игроке
        const playerBadge = document.querySelector('.player-badge');
        if (playerBadge) {
            playerBadge.textContent = `Игрок ${this.config.playerNumber}`;
            playerBadge.className = `player-badge player-${this.config.playerNumber}`;
        }
        
        // Обновляем код комнаты
        const roomCodeElement = document.querySelector('.room-code-display');
        if (roomCodeElement) {
            roomCodeElement.textContent = this.config.roomCode;
        }
        
        // Если игрок 1, показываем код для шаринга
        if (this.isPlayer1) {
            const waitingScreen = document.getElementById('waitingScreen');
            if (waitingScreen) {
                const shareCodeElement = waitingScreen.querySelector('.room-code');
                if (shareCodeElement) {
                    shareCodeElement.textContent = this.config.roomCode;
                }
            }
        }
    }
    
    draw() {
        if (!this.gameState || !this.ctx) return;
        
        const ctx = this.ctx;
        const canvas = this.canvas;
        
        // Очищаем канвас
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Рисуем фон
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Рисуем центральную линию
        ctx.setLineDash([10, 10]);
        ctx.beginPath();
        ctx.moveTo(canvas.width / 2, 0);
        ctx.lineTo(canvas.width / 2, canvas.height);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Рисуем ракетки
        if (this.gameState.player1) {
            ctx.fillStyle = '#4CAF50'; // Зеленый для игрока 1
            ctx.fillRect(20, this.gameState.player1.y, 10, 100);
            
            // Подсветка текущего игрока
            if (this.config.playerNumber === 1) {
                ctx.strokeStyle = '#FFEB3B';
                ctx.lineWidth = 3;
                ctx.strokeRect(15, this.gameState.player1.y - 5, 20, 110);
            }
        }
        
        if (this.gameState.player2) {
            ctx.fillStyle = '#2196F3'; // Синий для игрока 2
            ctx.fillRect(canvas.width - 30, this.gameState.player2.y, 10, 100);
            
            // Подсветка текущего игрока
            if (this.config.playerNumber === 2) {
                ctx.strokeStyle = '#FFEB3B';
                ctx.lineWidth = 3;
                ctx.strokeRect(canvas.width - 35, this.gameState.player2.y - 5, 20, 110);
            }
        }
        
        // Рисуем мяч
        if (this.gameState.ball) {
            ctx.beginPath();
            ctx.arc(
                this.gameState.ball.x,
                this.gameState.ball.y,
                8,
                0,
                Math.PI * 2
            );
            ctx.fillStyle = '#FF5722'; // Оранжевый мяч
            ctx.fill();
            
            // Обводка мяча
            ctx.strokeStyle = '#FFEB3B';
            ctx.lineWidth = 2;
            ctx.stroke();
        }
        
        // Рисуем счет
        ctx.fillStyle = '#FFF';
        ctx.font = 'bold 40px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(
            (this.gameState.player1?.score || 0) + ' : ' + (this.gameState.player2?.score || 0),
            canvas.width / 2,
            50
        );
    }
    
    startGameLoop() {
        // Обновляем состояние игры каждые 100ms
        this.updateInterval = setInterval(() => {
            this.updateGameState();
        }, 100);
        
        // Рисуем игру 60 раз в секунду
        const gameLoop = () => {
            this.draw();
            requestAnimationFrame(gameLoop);
        };
        
        gameLoop();
    }
    
    disconnect() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        if (this.moveInterval) {
            clearInterval(this.moveInterval);
        }
        
        if (this.isConnected) {
            try {
                const formData = new FormData();
                formData.append('room', this.config.roomCode);
                formData.append('player_id', this.playerId);
                
                fetch('api.php?action=leave_game', {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('Error disconnecting:', error);
            }
        }
    }
}

// Инициализация игры
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded, initializing game...');
    
    if (typeof CONFIG !== 'undefined') {
        const game = new PongGame(CONFIG);
        
        // Обработка выхода из игры
        const leaveBtn = document.getElementById('leaveBtn');
        if (leaveBtn) {
            leaveBtn.addEventListener('click', () => {
                if (confirm('Выйти из игры?')) {
                    game.disconnect();
                    window.location.href = 'index.php';
                }
            });
        }
        
        // Обработка закрытия страницы
        window.addEventListener('beforeunload', () => {
            game.disconnect();
        });
        
        // Обработка скрытия страницы (мобильные устройства)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                game.disconnect();
            }
        });
        
        // Функция для шаринга игры
        window.shareGame = function() {
            const gameUrl = window.location.origin + window.location.pathname + 
                          `?room=${CONFIG.roomCode}&player=2`;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Присоединяйся к Pong Multiplayer!',
                    text: `Сыграем в Pong? Код комнаты: ${CONFIG.roomCode}`,
                    url: gameUrl
                });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(gameUrl).then(() => {
                    alert('Ссылка скопирована! Отправь её другу.\nКод комнаты: ' + CONFIG.roomCode);
                });
            } else {
                prompt('Отправьте эту ссылку другу:', gameUrl);
            }
        };
    } else {
        console.error('CONFIG is not defined!');
        
        // Пробуем получить данные из URL
        const urlParams = new URLSearchParams(window.location.search);
        const roomCode = urlParams.get('room');
        const playerNumber = parseInt(urlParams.get('player')) || 1;
        
        if (roomCode) {
            const game = new PongGame({
                roomCode: roomCode,
                playerNumber: playerNumber,
                apiUrl: 'api.php'
            });
        }
    }
});
