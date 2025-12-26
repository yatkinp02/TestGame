class PongGame {
    constructor(config) {
        this.config = config;
        this.canvas = document.getElementById('gameCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.gameState = null;
        this.isPlayer1 = config.playerNumber === 1;
        this.isConnected = false;
        this.lastUpdate = 0;
        this.updateInterval = null;
        this.moveDirection = 0;
        this.keys = {};
        
        this.init();
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
            const width = Math.min(800, container.clientWidth - 40);
            const height = (width / 800) * 600;
            
            this.canvas.style.width = width + 'px';
            this.canvas.style.height = height + 'px';
        };
        
        resize();
        window.addEventListener('resize', resize);
    }
    
    setupControls() {
        // Кнопки на экране
        const upBtn = document.getElementById('upBtn');
        const downBtn = document.getElementById('downBtn');
        
        const handleStart = (direction) => {
            this.moveDirection = direction;
            this.sendPlayerPosition();
        };
        
        const handleEnd = () => {
            this.moveDirection = 0;
        };
        
        // Touch события
        upBtn.addEventListener('touchstart', (e) => {
            e.preventDefault();
            handleStart(-1);
        });
        
        upBtn.addEventListener('touchend', (e) => {
            e.preventDefault();
            handleEnd();
        });
        
        downBtn.addEventListener('touchstart', (e) => {
            e.preventDefault();
            handleStart(1);
        });
        
        downBtn.addEventListener('touchend', (e) => {
            e.preventDefault();
            handleEnd();
        });
        
        // Клавиатура (для тестирования на ПК)
        window.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowUp' || e.key === 'w') {
                this.keys.up = true;
                this.moveDirection = -1;
            }
            if (e.key === 'ArrowDown' || e.key === 's') {
                this.keys.down = true;
                this.moveDirection = 1;
            }
        });
        
        window.addEventListener('keyup', (e) => {
            if (e.key === 'ArrowUp' || e.key === 'w') {
                this.keys.up = false;
                if (!this.keys.down) this.moveDirection = 0;
            }
            if (e.key === 'ArrowDown' || e.key === 's') {
                this.keys.down = false;
                if (!this.keys.up) this.moveDirection = 0;
            }
        });
    }
    
    async connectToGame() {
        try {
            // Регистрируем игрока
            const formData = new FormData();
            formData.append('room', this.config.roomCode);
            formData.append('player_id', this.config.playerId);
            
            const response = await fetch(this.config.apiUrl + '?action=join_game', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.isConnected = true;
                this.gameState = data.game_state;
                
                // Если игрок 2 присоединился, скрываем экран ожидания
                if (this.config.playerNumber === 1 && data.game_state.player2.id) {
                    const waitingScreen = document.getElementById('waitingScreen');
                    if (waitingScreen) {
                        waitingScreen.style.display = 'none';
                    }
                }
                
                // Если игрок 1, показываем экран ожидания
                if (this.config.playerNumber === 2 && data.game_state.player1.id) {
                    const statusMessage = document.getElementById('statusMessage');
                    if (statusMessage) {
                        statusMessage.textContent = 'Игрок 1 найден! Начинаем игру...';
                    }
                }
                
                console.log('Успешно подключен как игрок', this.config.playerNumber);
            } else {
                console.error('Ошибка подключения:', data.error);
                alert('Не удалось подключиться к игре: ' + data.error);
            }
        } catch (error) {
            console.error('Ошибка сети:', error);
        }
    }
    
    async updateGameState() {
        if (!this.isConnected) return;
        
        try {
            const response = await fetch(
                `${this.config.apiUrl}?action=get_state&room=${this.config.roomCode}&_=${Date.now()}`
            );
            
            const data = await response.json();
            
            if (!data.error) {
                this.gameState = data;
                
                // Обновляем счет
                document.getElementById('score1').textContent = data.player1.score || 0;
                document.getElementById('score2').textContent = data.player2.score || 0;
                
                // Обновляем позицию игрока если есть движение
                if (this.moveDirection !== 0) {
                    this.sendPlayerPosition();
                }
                
                this.lastUpdate = Date.now();
            }
        } catch (error) {
            console.error('Ошибка обновления состояния:', error);
        }
    }
    
    async sendPlayerPosition() {
        if (!this.gameState || !this.isConnected) return;
        
        const playerKey = `player${this.config.playerNumber}`;
        const player = this.gameState[playerKey];
        
        if (!player) return;
        
        // Вычисляем новую позицию
        let newY = player.y + (this.moveDirection * 8);
        
        // Ограничиваем движение
        newY = Math.max(0, Math.min(500, newY));
        
        // Отправляем на сервер
        const formData = new FormData();
        formData.append('room', this.config.roomCode);
        formData.append('player_id', this.config.playerId);
        formData.append('player_number', this.config.playerNumber);
        formData.append('y', newY);
        
        try {
            await fetch(this.config.apiUrl + '?action=update_player', {
                method: 'POST',
                body: formData
            });
        } catch (error) {
            console.error('Ошибка отправки позиции:', error);
        }
    }
    
    draw() {
        if (!this.gameState) return;
        
        const ctx = this.ctx;
        const canvas = this.canvas;
        
        // Очищаем канвас
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Рисуем фон
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Центральная линия
        ctx.setLineDash([10, 10]);
        ctx.beginPath();
        ctx.moveTo(canvas.width / 2, 0);
        ctx.lineTo(canvas.width / 2, canvas.height);
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.setLineDash([]);
        
        // Ракетка игрока 1
        ctx.fillStyle = '#4CAF50';
        ctx.fillRect(20, this.gameState.player1.y, 10, 100);
        
        // Ракетка игрока 2
        ctx.fillStyle = '#2196F3';
        ctx.fillRect(canvas.width - 30, this.gameState.player2.y, 10, 100);
        
        // Мяч
        if (this.gameState.ball) {
            ctx.beginPath();
            ctx.arc(
                this.gameState.ball.x, 
                this.gameState.ball.y, 
                8, 0, Math.PI * 2
            );
            ctx.fillStyle = '#FF5722';
            ctx.fill();
            
            // Обводка мяча
            ctx.strokeStyle = '#FFEB3B';
            ctx.lineWidth = 2;
            ctx.stroke();
        }
        
        // Подсвечиваем текущего игрока
        const currentPlayer = this.gameState[`player${this.config.playerNumber}`];
        if (currentPlayer) {
            ctx.strokeStyle = '#FFEB3B';
            ctx.lineWidth = 3;
            
            if (this.config.playerNumber === 1) {
                ctx.strokeRect(15, currentPlayer.y - 5, 20, 110);
            } else {
                ctx.strokeRect(canvas.width - 35, currentPlayer.y - 5, 20, 110);
            }
        }
    }
    
    startGameLoop() {
        // Обновляем состояние игры каждые 100мс
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
        
        // Сообщаем серверу о выходе
        if (this.isConnected) {
            const formData = new FormData();
            formData.append('room', this.config.roomCode);
            formData.append('player_id', this.config.playerId);
            
            fetch(this.config.apiUrl + '?action=leave_game', {
                method: 'POST',
                body: formData
            });
        }
    }
}

// Инициализация игры когда страница загружена
document.addEventListener('DOMContentLoaded', () => {
    const game = new PongGame(CONFIG);
    
    // Обработка выхода из игры
    window.addEventListener('beforeunload', () => {
        game.disconnect();
    });
    
    // Обработка закрытия вкладки/браузера на мобильных
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            game.disconnect();
        }
    });
});