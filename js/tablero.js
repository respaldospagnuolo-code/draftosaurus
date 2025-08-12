/**
 * TableroManager.js - Gestión completa del tablero de juego
 * Integra con GameEngine y maneja toda la interfaz del tablero
 */

class TableroManager {
    constructor(gameEngine) {
        this.gameEngine = gameEngine;
        this.currentGameId = null;
        this.isTrackingMode = false;
        this.boardContainer = null;
        this.draggedDinosaur = null;
        this.validDropZones = [];
        this.touchStartPos = null;
        
        this.init();
    }

    init() {
        this.createBoardHTML();
        this.setupEventListeners();
        this.loadRecintoInfo();
    }

    createBoardHTML() {
        const contentContainer = document.querySelector('.content-container');
        
        const boardHTML = `
            <!-- Tablero de Juego Digital -->
            <div class="component game-board" id="game-board">
                <div class="game-info" id="game-info">
                    <div class="game-status">
                        <span class="round">Ronda 1/2</span>
                        <span class="turn">Turno 1/6</span>
                        <span class="current-player">Esperando...</span>
                    </div>
                </div>

                <div class="dice-display" id="dice-display">
                    <div class="dice-result">
                        <img src="img/dados/cara-dado-1.png" alt="Dado" id="dice-image">
                        <span class="dice-text" id="dice-text">Lanza el dado</span>
                    </div>
                </div>

                <div class="game-area">
                    <div class="players-boards" id="players-boards">
                        <!-- Los tableros se generan dinámicamente -->
                    </div>

                    <div class="current-hand" id="current-hand">
                        <h3>Tu mano:</h3>
                        <div class="hand-dinosaurs" id="hand-dinosaurs">
                            <!-- Los dinosaurios se generan dinámicamente -->
                        </div>
                    </div>

                    <div class="game-controls">
                        <button class="btn btn-naranja" onclick="tableroManager.rollDice()" id="roll-dice-btn">
                            Lanzar Dado
                        </button>
                        <button class="btn btn-verde" onclick="tableroManager.endTurn()" id="end-turn-btn">
                            Terminar Turno
                        </button>
                        <button class="btn btn-secondary" onclick="tableroManager.pauseGame()">
                            Pausar
                        </button>
                        <button class="btn btn-secondary" onclick="tableroManager.showGameMenu()">
                            Menú
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tablero de Seguimiento -->
            <div class="component tracking-board" id="tracking-board">
                <div class="tracking-controls" id="tracking-controls">
                    <div class="tracking-player-select">
                        <label for="tracking-player">Jugador:</label>
                        <select id="tracking-player">
                            <option value="">Seleccionar jugador...</option>
                        </select>
                    </div>
                    
                    <div class="tracking-dinosaur-select">
                        <label for="tracking-dinosaur">Dinosaurio:</label>
                        <select id="tracking-dinosaur">
                            <option value="">Seleccionar dinosaurio...</option>
                            <option value="1">T-Rex (Verde)</option>
                            <option value="2">Triceratops (Azul)</option>
                            <option value="3">Brontosaurus (Amarillo)</option>
                            <option value="4">Stegosaurus (Rojo)</option>
                            <option value="5">Pteranodon (Naranja)</option>
                            <option value="6">Parasaurolophus (Rosa)</option>
                        </select>
                    </div>
                    
                    <div class="tracking-recinto-select">
                        <label for="tracking-recinto">Recinto:</label>
                        <select id="tracking-recinto">
                            <option value="">Seleccionar recinto...</option>
                            <option value="1">Bosque de la Semejanza</option>
                            <option value="2">Prado de la Diferencia</option>
                            <option value="3">Pradera del Amor</option>
                            <option value="4">Trío Frondoso</option>
                            <option value="5">Rey de la Selva</option>
                            <option value="6">Isla Solitaria</option>
                            <option value="7">Río</option>
                        </select>
                    </div>
                    
                    <button class="btn btn-verde add-dinosaur-btn" onclick="tableroManager.addDinosaurTracking()">
                        Agregar
                    </button>
                    
                    <button class="btn btn-naranja" onclick="tableroManager.calculateAllScores()">
                        Calcular Puntos
                    </button>
                    
                    <button class="btn btn-secondary" onclick="tableroManager.clearTrackingBoard()">
                        Limpiar
                    </button>
                </div>

                <div class="players-boards" id="tracking-boards">
                    <!-- Los tableros de seguimiento se generan dinámicamente -->
                </div>
                
                <div class="tracking-results" id="tracking-results">
                    <!-- Resultados del modo seguimiento -->
                </div>
            </div>
        `;

        contentContainer.insertAdjacentHTML('beforeend', boardHTML);
        this.boardContainer = document.getElementById('game-board');
    }

    setupEventListeners() {
        // Drag and Drop
        document.addEventListener('dragstart', (e) => this.handleDragStart(e));
        document.addEventListener('dragover', (e) => this.handleDragOver(e));
        document.addEventListener('drop', (e) => this.handleDrop(e));
        document.addEventListener('dragend', (e) => this.handleDragEnd(e));

        // Touch events para móviles
        document.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: false });
        document.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        document.addEventListener('touchend', (e) => this.handleTouchEnd(e));

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // Resize handler
        window.addEventListener('resize', () => this.handleResize());

        // Clicks en recintos para modo tracking
        document.addEventListener('click', (e) => this.handleRecintoClick(e));
    }

    // ===== GESTIÓN DEL JUEGO =====
    startGame(gameData) {
        this.currentGameId = gameData.id;
        this.isTrackingMode = gameData.mode === 'seguimiento';
        
        if (this.isTrackingMode) {
            this.initTrackingMode(gameData);
        } else {
            this.initDigitalMode(gameData);
        }
    }

    initDigitalMode(gameData) {
        if (window.app) {
            window.app.showComponent('game-board');
        }
        
        this.setupPlayerBoards(gameData.numPlayers);
        this.updateGameInfo();
        this.updatePlayerHand([]);
        
        // Actualizar controles
        this.updateGameControls();
    }

    initTrackingMode(gameData) {
        if (window.app) {
            window.app.showComponent('tracking-board');
        }
        
        this.setupTrackingControls(gameData.numPlayers);
        this.setupTrackingBoards(gameData.numPlayers);
    }

    setupPlayerBoards(numPlayers) {
        const boardsContainer = document.getElementById('players-boards');
        if (!boardsContainer) return;

        let boardsHTML = '';
        
        for (let i = 0; i < numPlayers; i++) {
            boardsHTML += `
                <div class="player-board" id="player-board-${i}" data-player-id="${i}">
                    <div class="player-info">
                        <h3>Jugador ${i + 1}</h3>
                        <div class="player-score">Puntos: <span id="score-${i}">0</span></div>
                    </div>
                    <div class="board-recintos">
                        ${this.generateRecintosHTML(i)}
                    </div>
                </div>
            `;
        }
        
        boardsContainer.innerHTML = boardsHTML;
    }

    generateRecintosHTML(playerId) {
        const recintos = [
            { id: 1, name: 'Bosque de la Semejanza', maxCapacity: 6, description: 'Solo misma especie' },
            { id: 2, name: 'Prado de la Diferencia', maxCapacity: 6, description: 'Solo especies distintas' },
            { id: 3, name: 'Pradera del Amor', maxCapacity: 6, description: '5 pts por pareja' },
            { id: 4, name: 'Trío Frondoso', maxCapacity: 3, description: '7 pts si hay exactamente 3' },
            { id: 5, name: 'Rey de la Selva', maxCapacity: 1, description: '7 pts si tienes más de esa especie' },
            { id: 6, name: 'Isla Solitaria', maxCapacity: 1, description: '7 pts si es único en tu parque' },
            { id: 7, name: 'Río', maxCapacity: null, description: '1 pt por dinosaurio' }
        ];

        return recintos.map(recinto => `
            <div class="recinto tooltip" 
                 data-recinto-id="${recinto.id}" 
                 data-player-id="${playerId}"
                 data-max-capacity="${recinto.maxCapacity || 999}"
                 data-tooltip="${recinto.description}">
                <h4>${recinto.name}</h4>
                <div class="recinto-dinosaurs" id="recinto-${playerId}-${recinto.id}">
                    <!-- Dinosaurios se agregan aquí -->
                </div>
                <div class="recinto-capacity">
                    ${recinto.maxCapacity ? `0/${recinto.maxCapacity}` : '∞'}
                </div>
            </div>
        `).join('');
    }

    setupTrackingControls(numPlayers) {
        const playerSelect = document.getElementById('tracking-player');
        if (!playerSelect) return;

        let options = '<option value="">Seleccionar jugador...</option>';
        for (let i = 0; i < numPlayers; i++) {
            options += `<option value="${i}">Jugador ${i + 1}</option>`;
        }
        
        playerSelect.innerHTML = options;
    }

    setupTrackingBoards(numPlayers) {
        const boardsContainer = document.getElementById('tracking-boards');
        if (!boardsContainer) return;

        let boardsHTML = '';
        
        for (let i = 0; i < numPlayers; i++) {
            boardsHTML += `
                <div class="player-board" id="tracking-player-board-${i}" data-player-id="${i}">
                    <div class="player-info">
                        <h3>Jugador ${i + 1}</h3>
                        <div class="player-score">Puntos: <span id="tracking-score-${i}">0</span></div>
                    </div>
                    <div class="board-recintos">
                        ${this.generateRecintosHTML(i)}
                    </div>
                </div>
            `;
        }
        
        boardsContainer.innerHTML = boardsHTML;
    }

    // ===== DRAG AND DROP =====
    handleDragStart(e) {
        if (!e.target.classList.contains('hand-dinosaur')) return;
        
        this.draggedDinosaur = {
            element: e.target,
            dinosaurId: e.target.dataset.dinosaurId,
            dinosaurType: parseInt(e.target.dataset.dinosaurType)
        };
        
        e.target.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', this.draggedDinosaur.dinosaurId);
        
        this.highlightValidDropZones();
    }

    handleDragOver(e) {
        if (!this.draggedDinosaur) return;
        
        const recinto = e.target.closest('.recinto');
        if (recinto) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }
    }

    handleDrop(e) {
        if (!this.draggedDinosaur) return;
        
        const recinto = e.target.closest('.recinto');
        if (!recinto) return;
        
        e.preventDefault();
        
        const recintoId = parseInt(recinto.dataset.recintoId);
        const playerId = parseInt(recinto.dataset.playerId);
        
        this.placeDinosaur(this.draggedDinosaur.dinosaurType, recintoId, playerId);
    }

    handleDragEnd(e) {
        if (this.draggedDinosaur) {
            this.draggedDinosaur.element.classList.remove('dragging');
            this.draggedDinosaur = null;
        }
        
        this.clearDropZoneHighlights();
    }

    // ===== TOUCH EVENTS =====
    handleTouchStart(e) {
        if (!e.target.closest('.hand-dinosaur')) return;
        
        this.touchStartPos = {
            x: e.touches[0].clientX,
            y: e.touches[0].clientY
        };
        
        const dinosaur = e.target.closest('.hand-dinosaur');
        this.draggedDinosaur = {
            element: dinosaur,
            dinosaurId: dinosaur.dataset.dinosaurId,
            dinosaurType: parseInt(dinosaur.dataset.dinosaurType)
        };
        
        dinosaur.classList.add('dragging');
        this.highlightValidDropZones();
    }

    handleTouchMove(e) {
        if (!this.draggedDinosaur || !this.touchStartPos) return;
        
        e.preventDefault();
        
        const touch = e.touches[0];
        const element = document.elementFromPoint(touch.clientX, touch.clientY);
        
        // Resaltar recinto bajo el dedo
        const recintos = document.querySelectorAll('.recinto');
        recintos.forEach(r => r.classList.remove('touch-hover'));
        
        const recinto = element?.closest('.recinto');
        if (recinto) {
            recinto.classList.add('touch-hover');
        }
    }

    handleTouchEnd(e) {
        if (!this.draggedDinosaur) return;
        
        const touch = e.changedTouches[0];
        const element = document.elementFromPoint(touch.clientX, touch.clientY);
        const recinto = element?.closest('.recinto');
        
        if (recinto) {
            const recintoId = parseInt(recinto.dataset.recintoId);
            const playerId = parseInt(recinto.dataset.playerId);
            this.placeDinosaur(this.draggedDinosaur.dinosaurType, recintoId, playerId);
        }
        
        // Limpiar estado
        this.draggedDinosaur.element.classList.remove('dragging');
        this.draggedDinosaur = null;
        this.touchStartPos = null;
        this.clearDropZoneHighlights();
        
        const recintos = document.querySelectorAll('.recinto');
        recintos.forEach(r => r.classList.remove('touch-hover'));
    }

    highlightValidDropZones() {
        if (!this.gameEngine || !this.draggedDinosaur) return;
        
        const dinosaur = this.gameEngine.dinosaurTypes.find(d => d.id === this.draggedDinosaur.dinosaurType);
        if (!dinosaur) return;
        
        const recintos = document.querySelectorAll('.recinto');
        recintos.forEach(recinto => {
            const recintoId = parseInt(recinto.dataset.recintoId);
            const playerId = parseInt(recinto.dataset.playerId);
            
            if (this.gameEngine.canPlaceDinosaur(playerId, dinosaur, recintoId)) {
                recinto.classList.add('drop-zone');
            } else {
                recinto.classList.add('invalid-drop');
            }
        });
    }

    clearDropZoneHighlights() {
        const recintos = document.querySelectorAll('.recinto');
        recintos.forEach(recinto => {
            recinto.classList.remove('drop-zone', 'invalid-drop', 'touch-hover');
        });
    }

    // ===== COLOCACIÓN DE DINOSAURIOS =====
    placeDinosaur(dinosaurType, recintoId, playerId) {
        if (!this.gameEngine) return false;
        
        if (this.isTrackingMode) {
            return this.gameEngine.addDinosaurTracking(playerId, dinosaurType, recintoId);
        } else {
            const dinosaur = this.gameEngine.dinosaurTypes.find(d => d.id === dinosaurType);
            if (!dinosaur) return false;
            
            const currentPlayer = this.gameEngine.players[this.gameEngine.currentPlayer];
            const handDinosaur = currentPlayer.hand.find(d => d.id === dinosaurType);
            
            if (!handDinosaur) return false;
            
            const result = this.gameEngine.placeDinosaur(playerId, handDinosaur, recintoId);
            if (result.success) {
                this.updateUI();
                
                if (result.forcedToRiver && window.app) {
                    window.app.showNotification('Dinosaurio enviado al río por restricciones', 'warning');
                }
                
                // Verificar si el turno debe terminar
                if (currentPlayer.hand.length === 0) {
                    setTimeout(() => this.gameEngine.endTurn(), 500);
                }
            }
            
            return result.success;
        }
    }

    // ===== MODO SEGUIMIENTO =====
    addDinosaurTracking() {
        const playerId = parseInt(document.getElementById('tracking-player').value);
        const dinosaurType = parseInt(document.getElementById('tracking-dinosaur').value);
        const recintoId = parseInt(document.getElementById('tracking-recinto').value);
        
        if (isNaN(playerId) || isNaN(dinosaurType) || isNaN(recintoId)) {
            if (window.app) {
                window.app.showNotification('Por favor selecciona jugador, dinosaurio y recinto', 'warning');
            }
            return;
        }
        
        const success = this.gameEngine.addDinosaurTracking(playerId, dinosaurType, recintoId);
        
        if (success) {
            this.updateTrackingBoards();
            this.clearTrackingSelections();
            
            if (window.app) {
                window.app.showNotification('Dinosaurio agregado', 'success');
            }
        } else {
            if (window.app) {
                window.app.showNotification('No se puede colocar el dinosaurio ahí', 'error');
            }
        }
    }

    calculateAllScores() {
        if (!this.gameEngine) return;
        
        const scores = this.gameEngine.calculateAllScores();
        this.updateTrackingResults(scores);
        
        if (window.app) {
            window.app.showNotification('Puntuaciones calculadas', 'success');
        }
    }

    clearTrackingBoard() {
        if (!this.gameEngine) return;
        
        // Reinicializar el engine en modo tracking
        const numPlayers = this.gameEngine.players.length;
        this.gameEngine.initTrackingMode(numPlayers);
        this.updateTrackingBoards();
        this.clearTrackingResults();
        
        if (window.app) {
            window.app.showNotification('Tablero limpiado', 'info');
        }
    }

    clearTrackingSelections() {
        document.getElementById('tracking-player').value = '';
        document.getElementById('tracking-dinosaur').value = '';
        document.getElementById('tracking-recinto').value = '';
    }

    updateTrackingBoards() {
        if (!this.gameEngine) return;
        
        this.gameEngine.players.forEach((player, index) => {
            const playerBoard = document.getElementById(`tracking-player-board-${index}`);
            if (playerBoard) {
                this.renderPlayerBoard(playerBoard, player, index, true);
            }
        });
    }

    updateTrackingResults(scores) {
        const resultsContainer = document.getElementById('tracking-results');
        if (!resultsContainer || !scores) return;
        
        const sortedPlayers = this.gameEngine.players
            .map((player, index) => ({ ...player, index, score: scores[index] || 0 }))
            .sort((a, b) => b.score - a.score);
        
        resultsContainer.innerHTML = `
            <div class="tracking-results-container">
                <h3>Puntuaciones Calculadas</h3>
                <div class="scores-list">
                    ${sortedPlayers.map((player, position) => `
                        <div class="player-final-score ${position === 0 ? 'winner' : ''}">
                            <span class="player-name">${player.name}</span>
                            <span class="player-score">${player.score} pts</span>
                        </div>
                    `).join('')}
                </div>
                ${sortedPlayers.length > 0 ? `
                    <div class="winner-announcement">
                        <span class="winner-text">🏆 Ganador: ${sortedPlayers[0].name}</span>
                    </div>
                ` : ''}
            </div>
        `;
    }

    clearTrackingResults() {
        const resultsContainer = document.getElementById('tracking-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }
    }

    // ===== CONTROLES DEL JUEGO =====
    rollDice() {
        if (!this.gameEngine || this.isTrackingMode) return;
        
        const result = this.gameEngine.rollDice();
        this.updateDiceDisplay();
        this.updateGameControls();
        
        if (window.app) {
            window.app.showNotification(`Dado: ${result.description}`, 'info');
        }
        
        return result;
    }

    endTurn() {
        if (!this.gameEngine || this.isTrackingMode) return;
        
        this.gameEngine.endTurn();
        this.updateUI();
        
        return true;
    }

    pauseGame() {
        if (!this.gameEngine) return;
        
        this.gameEngine.pauseGame();
        
        if (window.app) {
            window.app.showNotification('Juego pausado', 'info');
        }
        
        return true;
    }

    showGameMenu() {
        if (window.app) {
            const confirmed = confirm('¿Volver al menú principal? Se perderá el progreso del juego.');
            if (confirmed) {
                window.app.showMain();
            }
        }
    }

    // ===== ACTUALIZACIÓN DE UI =====
    updateUI() {
        if (!this.gameEngine) return;
        
        this.updateGameInfo();
        this.updatePlayerBoards();
        this.updateCurrentPlayerHand();
        this.updateDiceDisplay();
        this.updateGameControls();
    }

    updateGameInfo() {
        const gameInfo = document.getElementById('game-info');
        if (!gameInfo || !this.gameEngine) return;
        
        const activePlayer = this.gameEngine.players[this.gameEngine.currentPlayer];
        gameInfo.innerHTML = `
            <div class="game-status">
                <span class="round">Ronda ${this.gameEngine.currentRound}/2</span>
                <span class="turn">Turno ${this.gameEngine.currentTurn}/6</span>
                <span class="current-player">Turno de: ${activePlayer?.name || 'Jugador'}</span>
            </div>
        `;
    }

    updatePlayerBoards() {
        if (!this.gameEngine) return;
        
        this.gameEngine.players.forEach((player, index) => {
            const playerBoard = document.getElementById(`player-board-${index}`);
            if (playerBoard) {
                this.renderPlayerBoard(playerBoard, player, index);
            }
        });
    }

    renderPlayerBoard(container, player, playerIndex, isTracking = false) {
        const isCurrentPlayer = playerIndex === this.gameEngine.currentPlayer && !isTracking;
        const scoreElement = isTracking ? `tracking-score-${playerIndex}` : `score-${playerIndex}`;
        
        container.className = `player-board ${isCurrentPlayer ? 'current-player' : ''}`;
        
        // Actualizar información del jugador
        const playerInfo = container.querySelector('.player-info');
        if (playerInfo) {
            playerInfo.innerHTML = `
                <h3>${player.name}</h3>
                <div class="player-score">Puntos: <span id="${scoreElement}">${player.score || 0}</span></div>
            `;
        }
        
        // Actualizar recintos
        this.gameEngine.recintos.forEach(recinto => {
            const recintoContainer = document.getElementById(`recinto-${playerIndex}-${recinto.id}`);
            if (recintoContainer) {
                const dinosaurs = player.board[recinto.id] || [];
                recintoContainer.innerHTML = dinosaurs.map(dino => `
                    <div class="dinosaur-piece ${dino.cssClass || dino.color}" 
                         title="${dino.name} (R${dino.round || '?'}T${dino.turn || '?'})">
                        ${this.getDinosaurSymbol(dino.name)}
                    </div>
                `).join('');
                
                // Actualizar contador de capacidad
                const capacityElement = recintoContainer.parentElement.querySelector('.recinto-capacity');
                if (capacityElement) {
                    const maxCapacity = recinto.maxCapacity;
                    const currentCount = dinosaurs.length;
                    capacityElement.textContent = maxCapacity ? `${currentCount}/${maxCapacity}` : '∞';
                }
            }
        });
    }

    getDinosaurSymbol(name) {
        const symbols = {
            'T-Rex': '🦖',
            'Triceratops': '🦕',
            'Brontosaurus': '🦴',
            'Stegosaurus': '🛡️',
            'Pteranodon': '🦅',
            'Parasaurolophus': '🎺'
        };
        return symbols[name] || name.charAt(0);
    }

    updateCurrentPlayerHand() {
        const hand = document.getElementById('current-hand');
        if (!hand || !this.gameEngine || this.isTrackingMode) return;
        
        const currentPlayer = this.gameEngine.players[this.gameEngine.currentPlayer];
        if (!currentPlayer) return;
        
        const handContainer = document.getElementById('hand-dinosaurs');
        if (handContainer) {
            handContainer.innerHTML = currentPlayer.hand.map(dino => `
                <div class="hand-dinosaur" 
                     data-dinosaur-id="${dino.uniqueId}"
                     data-dinosaur-type="${dino.id}"
                     draggable="true">
                    <img src="${dino.icon}" alt="${dino.name}">
                    <span>${dino.name}</span>
                </div>
            `).join('');
        }
    }

    updateDiceDisplay() {
        const diceDisplay = document.getElementById('dice-display');
        if (!diceDisplay || !this.gameEngine) return;
        
        const restriction = this.gameEngine.currentDiceRestriction;
        const diceImage = document.getElementById('dice-image');
        const diceText = document.getElementById('dice-text');
        
        if (restriction) {
            if (diceImage) diceImage.src = restriction.icon;
            if (diceText) diceText.textContent = restriction.description;
        } else {
            if (diceImage) diceImage.src = 'img/dados/cara-dado-1.png';
            if (diceText) diceText.textContent = 'Lanza el dado';
        }
    }

    updateGameControls() {
        const rollBtn = document.getElementById('roll-dice-btn');
        const endTurnBtn = document.getElementById('end-turn-btn');
        
        if (!this.gameEngine || this.isTrackingMode) return;
        
        // Habilitar/deshabilitar botones según el estado del juego
        if (rollBtn) {
            rollBtn.disabled = !!this.gameEngine.currentDiceRestriction;
            rollBtn.textContent = this.gameEngine.currentDiceRestriction ? 'Dado Lanzado' : 'Lanzar Dado';
        }
        
        if (endTurnBtn) {
            const currentPlayer = this.gameEngine.players[this.gameEngine.currentPlayer];
            endTurnBtn.disabled = !currentPlayer || currentPlayer.hand.length === 6;
        }
    }

    // ===== UTILIDADES =====
    handleKeyboard(e) {
        if (!this.gameEngine) return;
        
        switch (e.key) {
            case ' ': // Espacio para lanzar dado
                if (!this.isTrackingMode && !this.gameEngine.currentDiceRestriction) {
                    e.preventDefault();
                    this.rollDice();
                }
                break;
            case 'Enter': // Enter para terminar turno
                if (!this.isTrackingMode) {
                    e.preventDefault();
                    this.endTurn();
                }
                break;
            case 'Escape': // Escape para menú
                if (window.app) {
                    this.showGameMenu();
                }
                break;
        }
    }

    handleResize() {
        // Ajustar diseño según el tamaño de pantalla
        const gameArea = document.querySelector('.game-area');
        if (!gameArea) return;
        
        const width = window.innerWidth;
        
        if (width <= 768) {
            gameArea.classList.add('mobile-layout');
        } else {
            gameArea.classList.remove('mobile-layout');
        }
        
        // Reajustar tableros si es necesario
        this.adjustBoardLayout();
    }

    adjustBoardLayout() {
        const playerBoards = document.querySelectorAll('.player-board');
        playerBoards.forEach(board => {
            const recintos = board.querySelectorAll('.recinto');
            recintos.forEach(recinto => {
                // Ajustar tamaño de dinosaurios según espacio disponible
                const dinosaurs = recinto.querySelectorAll('.dinosaur-piece');
                if (dinosaurs.length > 6) {
                    recinto.classList.add('overcrowded');
                } else {
                    recinto.classList.remove('overcrowded');
                }
            });
        });
    }

    handleRecintoClick(e) {
        if (!this.isTrackingMode) return;
        
        const recinto = e.target.closest('.recinto');
        if (!recinto) return;
        
        // Auto-rellenar selección en modo tracking
        const recintoId = recinto.dataset.recintoId;
        const playerId = recinto.dataset.playerId;
        
        const playerSelect = document.getElementById('tracking-player');
        const recintoSelect = document.getElementById('tracking-recinto');
        
        if (playerSelect && playerId) {
            playerSelect.value = playerId;
        }
        
        if (recintoSelect && recintoId) {
            recintoSelect.value = recintoId;
        }
    }

    loadRecintoInfo() {
        // Cargar información adicional de recintos si es necesario
        if (!this.gameEngine) return;
        
        this.recintoDescriptions = {
            1: 'Bosque de la Semejanza: Solo dinosaurios de la misma especie. Puntos por cantidad.',
            2: 'Prado de la Diferencia: Solo especies distintas. Puntos por cantidad.',
            3: 'Pradera del Amor: Cualquier especie. 5 puntos por pareja.',
            4: 'Trío Frondoso: Máximo 3 dinosaurios. 7 puntos si hay exactamente 3.',
            5: 'Rey de la Selva: Solo 1 dinosaurio. 7 puntos si tienes más de esa especie.',
            6: 'Isla Solitaria: Solo 1 dinosaurio. 7 puntos si es único en tu parque.',
            7: 'Río: Sin límite. 1 punto por dinosaurio.'
        };
    }

    // ===== EFECTOS VISUALES =====
    showPlacementEffect(recintoElement, success = true) {
        if (!recintoElement) return;
        
        const effectClass = success ? 'placement-success' : 'placement-failed';
        recintoElement.classList.add(effectClass);
        
        setTimeout(() => {
            recintoElement.classList.remove(effectClass);
        }, 500);
    }

    animateDinosaurPlacement(dinosaurElement) {
        if (!dinosaurElement) return;
        
        dinosaurElement.style.transform = 'scale(0)';
        dinosaurElement.style.opacity = '0';
        
        setTimeout(() => {
            dinosaurElement.style.transition = 'all 0.3s ease';
            dinosaurElement.style.transform = 'scale(1)';
            dinosaurElement.style.opacity = '1';
        }, 50);
    }

    showTurnTransition() {
        const overlay = document.createElement('div');
        overlay.className = 'turn-transition';
        overlay.innerHTML = `
            <div class="transition-content">
                <h3>Turno ${this.gameEngine.currentTurn}</h3>
                <p>Jugador: ${this.gameEngine.players[this.gameEngine.currentPlayer]?.name}</p>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        setTimeout(() => {
            overlay.remove();
        }, 2000);
    }

    // ===== GESTIÓN DE ERRORES =====
    handleGameError(error) {
        console.error('Error en el tablero:', error);
        
        if (window.app) {
            window.app.showNotification('Error en el juego: ' + error.message, 'error');
        }
        
        // Intentar recuperar el estado del juego
        this.recoverGameState();
    }

    recoverGameState() {
        try {
            if (this.gameEngine) {
                this.updateUI();
            }
        } catch (error) {
            console.error('Error recuperando estado:', error);
            
            if (window.app) {
                window.app.showNotification('Error crítico. Regresando al menú principal.', 'error');
                setTimeout(() => window.app.showMain(), 2000);
            }
        }
    }

    // ===== GUARDADO Y CARGA =====
    saveGameState() {
        if (!this.gameEngine || this.isTrackingMode) return;
        
        const gameState = {
            gameId: this.currentGameId,
            timestamp: new Date().toISOString(),
            state: this.gameEngine.getGameState()
        };
        
        try {
            localStorage.setItem('draftosaurus_game_state', JSON.stringify(gameState));
        } catch (error) {
            console.warn('No se pudo guardar el estado del juego:', error);
        }
    }

    loadGameState() {
        try {
            const savedState = localStorage.getItem('draftosaurus_game_state');
            if (savedState) {
                const gameState = JSON.parse(savedState);
                
                // Verificar que el estado no sea muy antiguo (más de 1 hora)
                const timestamp = new Date(gameState.timestamp);
                const now = new Date();
                const hoursDiff = (now - timestamp) / (1000 * 60 * 60);
                
                if (hoursDiff < 1) {
                    return gameState.state;
                } else {
                    localStorage.removeItem('draftosaurus_game_state');
                }
            }
        } catch (error) {
            console.warn('Error cargando estado del juego:', error);
            localStorage.removeItem('draftosaurus_game_state');
        }
        
        return null;
    }

    clearSavedState() {
        try {
            localStorage.removeItem('draftosaurus_game_state');
        } catch (error) {
            console.warn('Error limpiando estado guardado:', error);
        }
    }

    // ===== ESTADÍSTICAS Y ANÁLISIS =====
    getGameStatistics() {
        if (!this.gameEngine) return null;
        
        return {
            gameId: this.currentGameId,
            currentRound: this.gameEngine.currentRound,
            currentTurn: this.gameEngine.currentTurn,
            totalMoves: this.gameEngine.gameHistory.length,
            playerScores: this.gameEngine.players.map(p => p.score),
            averageScore: this.gameEngine.players.reduce((sum, p) => sum + p.score, 0) / this.gameEngine.players.length,
            topScore: Math.max(...this.gameEngine.players.map(p => p.score)),
            gameProgress: ((this.gameEngine.currentRound - 1) * 6 + this.gameEngine.currentTurn) / 12 * 100
        };
    }

    generateGameReport() {
        const stats = this.getGameStatistics();
        if (!stats) return '';
        
        return `
            <div class="game-report">
                <h3>Reporte de Partida</h3>
                <div class="report-stats">
                    <div class="stat">
                        <label>Progreso:</label>
                        <span>${stats.gameProgress.toFixed(1)}%</span>
                    </div>
                    <div class="stat">
                        <label>Movimientos totales:</label>
                        <span>${stats.totalMoves}</span>
                    </div>
                    <div class="stat">
                        <label>Puntuación promedio:</label>
                        <span>${stats.averageScore.toFixed(1)}</span>
                    </div>
                    <div class="stat">
                        <label>Puntuación máxima:</label>
                        <span>${stats.topScore}</span>
                    </div>
                </div>
            </div>
        `;
    }

    // ===== ACCESIBILIDAD =====
    setupAccessibility() {
        // Agregar soporte para lectores de pantalla
        const recintos = document.querySelectorAll('.recinto');
        recintos.forEach((recinto, index) => {
            recinto.setAttribute('tabindex', '0');
            recinto.setAttribute('role', 'button');
            recinto.setAttribute('aria-label', `Recinto ${recinto.querySelector('h4')?.textContent}`);
            
            recinto.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    recinto.click();
                }
            });
        });
        
        // Agregar descripciones para drag and drop
        const handDinosaurs = document.querySelectorAll('.hand-dinosaur');
        handDinosaurs.forEach(dinosaur => {
            const name = dinosaur.querySelector('span')?.textContent || 'Dinosaurio';
            dinosaur.setAttribute('aria-label', `Arrastra ${name} a un recinto`);
            dinosaur.setAttribute('tabindex', '0');
        });
    }

    // ===== MODO DEBUG =====
    enableDebugMode() {
        if (typeof window.DEBUG_MODE === 'undefined') {
            window.DEBUG_MODE = true;
        }
        
        // Agregar panel de debug
        const debugPanel = document.createElement('div');
        debugPanel.id = 'debug-panel';
        debugPanel.innerHTML = `
            <div class="debug-header">
                <h4>Debug Panel</h4>
                <button onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <div class="debug-content">
                <button onclick="tableroManager.debugShowGameState()">Ver Estado</button>
                <button onclick="tableroManager.debugSimulateTurn()">Simular Turno</button>
                <button onclick="tableroManager.debugAddDinosaur()">Agregar Dinosaurio</button>
                <button onclick="tableroManager.debugCalculateScores()">Calcular Puntos</button>
            </div>
        `;
        
        debugPanel.style.cssText = `
            position: fixed;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 10px;
            border-radius: 5px;
            z-index: 9999;
            font-size: 12px;
        `;
        
        document.body.appendChild(debugPanel);
    }

    debugShowGameState() {
        if (this.gameEngine) {
            console.log('Estado del juego:', this.gameEngine.getGameState());
        }
    }

    debugSimulateTurn() {
        if (this.gameEngine && !this.isTrackingMode) {
            // Simular colocación automática
            const currentPlayer = this.gameEngine.players[this.gameEngine.currentPlayer];
            if (currentPlayer.hand.length > 0) {
                const dinosaur = currentPlayer.hand[0];
                const randomRecinto = Math.floor(Math.random() * 7) + 1;
                this.placeDinosaur(dinosaur.id, randomRecinto, this.gameEngine.currentPlayer);
            }
        }
    }

    debugAddDinosaur() {
        if (this.gameEngine) {
            const randomPlayer = Math.floor(Math.random() * this.gameEngine.players.length);
            const randomDinosaur = Math.floor(Math.random() * 6) + 1;
            const randomRecinto = Math.floor(Math.random() * 7) + 1;
            
            if (this.isTrackingMode) {
                this.gameEngine.addDinosaurTracking(randomPlayer, randomDinosaur, randomRecinto);
                this.updateTrackingBoards();
            }
        }
    }

    debugCalculateScores() {
        if (this.gameEngine) {
            this.gameEngine.players.forEach((player, index) => {
                const score = this.gameEngine.calculateScore(index);
                console.log(`${player.name}: ${score} puntos`);
            });
            this.updateUI();
        }
    }

    // ===== CLEANUP =====
    cleanup() {
        // Limpiar event listeners
        document.removeEventListener('dragstart', this.handleDragStart);
        document.removeEventListener('dragover', this.handleDragOver);
        document.removeEventListener('drop', this.handleDrop);
        document.removeEventListener('dragend', this.handleDragEnd);
        document.removeEventListener('touchstart', this.handleTouchStart);
        document.removeEventListener('touchmove', this.handleTouchMove);
        document.removeEventListener('touchend', this.handleTouchEnd);
        document.removeEventListener('keydown', this.handleKeyboard);
        window.removeEventListener('resize', this.handleResize);
        
        // Limpiar referencias
        this.gameEngine = null;
        this.draggedDinosaur = null;
        this.validDropZones = [];
        
        // Limpiar estado guardado
        this.clearSavedState();
    }

    // ===== INTEGRACIÓN CON BACKEND =====
    async syncWithServer() {
        if (!this.currentGameId || this.isTrackingMode) return;
        
        try {
            const response = await fetch(`api/game/status.php?gameId=${this.currentGameId}`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    // Sincronizar estado con servidor
                    this.updateFromServerState(data.data);
                }
            }
        } catch (error) {
            console.error('Error sincronizando con servidor:', error);
        }
    }

    updateFromServerState(serverState) {
        if (!this.gameEngine || !serverState) return;
        
        // Actualizar estado del juego desde el servidor
        if (serverState.rondaActual !== undefined) {
            this.gameEngine.currentRound = serverState.rondaActual;
        }
        
        if (serverState.turnoActual !== undefined) {
            this.gameEngine.currentTurn = serverState.turnoActual;
        }
        
        if (serverState.jugadorActivo !== undefined) {
            this.gameEngine.currentPlayer = serverState.jugadorActivo;
        }
        
        // Actualizar UI
        this.updateUI();
    }
}

// ===== FUNCIONES GLOBALES =====
let tableroManager;

// Inicializar TableroManager cuando esté disponible
document.addEventListener('DOMContentLoaded', function() {
    // Se inicializará desde app.js cuando se cree el GameEngine
});

// Funciones auxiliares para CSS
function addTableroStyles() {
    if (document.querySelector('#tablero-dynamic-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'tablero-dynamic-styles';
    style.textContent = `
        .recinto-capacity {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.7rem;
            background: rgba(0,0,0,0.7);
            color: var(--color-primary);
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .placement-success {
            animation: successPulse 0.5s ease;
        }
        
        .placement-failed {
            animation: failShake 0.5s ease;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); border-color: var(--color-success); }
            100% { transform: scale(1); }
        }
        
        @keyframes failShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .turn-transition {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeInOut 2s ease;
        }
        
        .transition-content {
            text-align: center;
            color: var(--color-text-light);
            font-family: 'Oswald', sans-serif;
        }
        
        .transition-content h3 {
            font-size: 2rem;
            color: var(--color-primary);
            margin-bottom: 0.5rem;
        }
        
        @keyframes fadeInOut {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }
        
        .overcrowded .dinosaur-piece {
            width: 20px;
            height: 20px;
            font-size: 0.6rem;
        }
        
        .mobile-layout .game-area {
            grid-template-columns: 1fr;
            padding: 120px 10px 10px;
        }
        
        .mobile-layout .players-boards {
            grid-template-columns: 1fr;
        }
        
        .touch-hover {
            background: rgba(76, 175, 80, 0.3) !important;
            transform: scale(1.02);
        }
        
        .tracking-results-container {
            background: rgba(0,0,0,0.8);
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
            border: 2px solid var(--color-primary);
        }
        
        .winner-text {
            font-family: 'Oswald', sans-serif;
            font-size: 1.2rem;
            color: var(--color-success);
            text-align: center;
            display: block;
            margin-top: 15px;
        }
        
        .game-report {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255,241,199,0.2);
        }
        
        .stat label {
            font-weight: 500;
        }
        
        .stat span {
            color: var(--color-primary);
            font-weight: 600;
        }
    `;
    
    document.head.appendChild(style);
}

// Agregar estilos cuando se carga el documento
document.addEventListener('DOMContentLoaded', addTableroStyles);

// Hacer disponible globalmente
if (typeof window !== 'undefined') {
    window.TableroManager = TableroManager;
}

// Exportar para Node.js si está disponible
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TableroManager;
}
            case '