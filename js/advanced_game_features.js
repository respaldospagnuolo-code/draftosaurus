/**
 * Funcionalidades Avanzadas para el Sistema de Juego
 * Incluye: Guardado automático, Recuperación de partidas, Exportación de datos
 */

// Extensión para el TableroManager
class AdvancedGameFeatures {
    constructor(gameEngine, tableroManager) {
        this.gameEngine = gameEngine;
        this.tableroManager = tableroManager;
        this.autoSaveInterval = null;
        this.backupInterval = null;
        
        this.init();
    }

    init() {
        this.setupAutoSave();
        this.setupKeyboardShortcuts();
        this.setupConnectionMonitoring();
    }

    // ===== GUARDADO AUTOMÁTICO =====
    setupAutoSave() {
        if (this.gameEngine.isTrackingMode) return;
        
        // Guardar cada 30 segundos
        this.autoSaveInterval = setInterval(() => {
            this.saveGameState();
        }, 30000);
        
        // Guardar al cerrar/recargar página
        window.addEventListener('beforeunload', (e) => {
            this.saveGameState();
            e.returnValue = '¿Estás seguro de que quieres salir? Se perderá el progreso no guardado.';
        });
        
        // Backup en localStorage cada 10 segundos
        this.backupInterval = setInterval(() => {
            this.createLocalBackup();
        }, 10000);
    }

    async saveGameState() {
        if (!this.gameEngine || this.gameEngine.isTrackingMode) return;
        
        try {
            const gameState = this.gameEngine.getGameState();
            
            const response = await fetch('api/game/save_load_apis.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    gameId: gameState.game?.id,
                    state: gameState
                })
            });
            
            if (response.ok) {
                console.log('Game state saved successfully');
            }
        } catch (error) {
            console.warn('Failed to save game state:', error);
            // Fallback a localStorage
            this.createLocalBackup();
        }
    }

    createLocalBackup() {
        if (!this.gameEngine) return;
        
        try {
            const gameState = this.gameEngine.getGameState();
            const backup = {
                timestamp: new Date().toISOString(),
                state: gameState
            };
            
            localStorage.setItem('draftosaurus_backup', JSON.stringify(backup));
        } catch (error) {
            console.warn('Failed to create local backup:', error);
        }
    }

    async loadGameState(gameId) {
        try {
            const response = await fetch(`api/game/save_load_apis.php?gameId=${gameId}`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data) {
                    this.gameEngine.loadGameState(data.data);
                    this.tableroManager.updateUI();
                    return true;
                }
            }
        } catch (error) {
            console.warn('Failed to load game state:', error);
        }
        
        // Intentar cargar desde localStorage
        return this.loadLocalBackup();
    }

    loadLocalBackup() {
        try {
            const backup = localStorage.getItem('draftosaurus_backup');
            if (backup) {
                const data = JSON.parse(backup);
                const backupAge = new Date() - new Date(data.timestamp);
                
                // Solo cargar si el backup es de menos de 1 hora
                if (backupAge < 3600000) {
                    this.gameEngine.loadGameState(data.state);
                    this.tableroManager.updateUI();
                    
                    if (window.app) {
                        window.app.showNotification('Partida recuperada desde backup local', 'info');
                    }
                    return true;
                }
            }
        } catch (error) {
            console.warn('Failed to load local backup:', error);
        }
        
        return false;
    }

    // ===== ATAJOS DE TECLADO =====
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Solo activar si no estamos en un input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            // Ctrl/Cmd + S para guardar
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveGameState();
                if (window.app) {
                    window.app.showNotification('Partida guardada', 'success');
                }
            }
            
            // Ctrl/Cmd + Z para deshacer
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                this.undoLastMove();
            }
            
            // Ctrl/Cmd + Shift + Z para rehacer
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && e.shiftKey) {
                e.preventDefault();
                this.redoMove();
            }
            
            // R para lanzar dado
            if (e.key === 'r' || e.key === 'R') {
                e.preventDefault();
                if (this.tableroManager && !this.gameEngine.isTrackingMode) {
                    this.tableroManager.rollDice();
                }
            }
            
            // Escape para pausar
            if (e.key === 'Escape') {
                e.preventDefault();
                this.togglePause();
            }
            
            // F para pantalla completa
            if (e.key === 'f' || e.key === 'F') {
                e.preventDefault();
                this.toggleFullscreen();
            }
        });
    }

    // ===== DESHACER/REHACER =====
    undoLastMove() {
        if (!this.gameEngine || this.gameEngine.isTrackingMode) return;
        
        const history = this.gameEngine.gameHistory;
        if (history.length === 0) return;
        
        const lastMove = history.pop();
        
        // Revertir el movimiento
        const player = this.gameEngine.players[lastMove.playerId];
        const recintoId = lastMove.recintoId;
        const dinosaurs = player.board[recintoId];
        
        if (dinosaurs.length > 0) {
            const removedDinosaur = dinosaurs.pop();
            
            // Devolver a la mano si no es modo tracking
            if (!this.gameEngine.isTrackingMode) {
                player.hand.push(removedDinosaur);
            }
            
            this.tableroManager.updateUI();
            
            if (window.app) {
                window.app.showNotification('Movimiento deshecho', 'info');
            }
        }
    }

    redoMove() {
        // TODO: Implementar sistema de redo más complejo
        if (window.app) {
            window.app.showNotification('Función de rehacer no disponible', 'warning');
        }
    }

    // ===== PAUSA =====
    togglePause() {
        if (!this.gameEngine || this.gameEngine.isTrackingMode) return;
        
        const gameArea = document.querySelector('.game-area');
        if (gameArea) {
            const isPaused = gameArea.classList.contains('game-paused');
            
            if (isPaused) {
                gameArea.classList.remove('game-paused');
                if (window.app) {
                    window.app.showNotification('Juego reanudado', 'info');
                }
            } else {
                gameArea.classList.add('game-paused');
                if (window.app) {
                    window.app.showNotification('Juego pausado - Presiona ESC para continuar', 'info');
                }
            }
        }
    }

    // ===== PANTALLA COMPLETA =====
    toggleFullscreen() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            document.documentElement.requestFullscreen().catch(err => {
                console.warn('Error entering fullscreen:', err);
            });
        }
    }

    // ===== MONITOREO DE CONEXIÓN =====
    setupConnectionMonitoring() {
        let isOnline = navigator.onLine;
        
        window.addEventListener('online', () => {
            if (!isOnline) {
                isOnline = true;
                if (window.app) {
                    window.app.showNotification('Conexión restaurada', 'success');
                }
                
                // Intentar sincronizar datos pendientes
                this.syncPendingData();
            }
        });
        
        window.addEventListener('offline', () => {
            isOnline = false;
            if (window.app) {
                window.app.showNotification('Sin conexión - Los datos se guardarán localmente', 'warning');
            }
        });
    }

    async syncPendingData() {
        // Sincronizar movimientos pendientes desde localStorage
        try {
            const pendingMoves = localStorage.getItem('draftosaurus_pending_moves');
            if (pendingMoves) {
                const moves = JSON.parse(pendingMoves);
                
                for (const move of moves) {
                    await this.gameEngine.sendMoveToServer(move);
                }
                
                localStorage.removeItem('draftosaurus_pending_moves');
                
                if (window.app) {
                    window.app.showNotification(`${moves.length} movimientos sincronizados`, 'success');
                }
            }
        } catch (error) {
            console.warn('Failed to sync pending data:', error);
        }
    }

    // ===== EXPORTACIÓN DE DATOS =====
    exportGameData(format = 'json') {
        if (!this.gameEngine) return;
        
        const gameState = this.gameEngine.getGameState();
        const exportData = {
            game: gameState.game,
            players: gameState.players.map(player => ({
                name: player.name,
                score: player.score,
                board: player.board
            })),
            history: gameState.gameHistory,
            timestamp: new Date().toISOString(),
            version: '1.0.0'
        };
        
        switch (format) {
            case 'json':
                this.downloadJSON(exportData);
                break;
            case 'csv':
                this.downloadCSV(exportData);
                break;
            case 'txt':
                this.downloadTXT(exportData);
                break;
            default:
                console.warn('Unsupported export format:', format);
        }
    }

    downloadJSON(data) {
        const json = JSON.stringify(data, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        this.downloadBlob(blob, `draftosaurus_game_${Date.now()}.json`);
    }

    downloadCSV(data) {
        let csv = 'Jugador,Puntuación,Recinto,Dinosaurios\n';
        
        data.players.forEach(player => {
            Object.entries(player.board).forEach(([recintoId, dinosaurs]) => {
                const recintoName = this.gameEngine.getRecintoById(parseInt(recintoId))?.name || 'Desconocido';
                const dinoList = dinosaurs.map(d => d.name).join(';');
                csv += `"${player.name}",${player.score},"${recintoName}","${dinoList}"\n`;
            });
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        this.downloadBlob(blob, `draftosaurus_game_${Date.now()}.csv`);
    }

    downloadTXT(data) {
        let txt = `DRAFTOSAURUS - RESUMEN DE PARTIDA\n`;
        txt += `Fecha: ${new Date(data.timestamp).toLocaleString()}\n`;
        txt += `Partida: ${data.game?.nombre || 'Sin nombre'}\n\n`;
        
        data.players.forEach((player, index) => {
            txt += `JUGADOR ${index + 1}: ${player.name}\n`;
            txt += `Puntuación: ${player.score} puntos\n`;
            txt += `Tablero:\n`;
            
            Object.entries(player.board).forEach(([recintoId, dinosaurs]) => {
                const recinto = this.gameEngine.getRecintoById(parseInt(recintoId));
                if (dinosaurs.length > 0) {
                    txt += `  ${recinto?.name || 'Recinto ' + recintoId}: `;
                    txt += dinosaurs.map(d => d.name).join(', ') + '\n';
                }
            });
            txt += '\n';
        });
        
        if (data.history.length > 0) {
            txt += 'HISTORIAL DE MOVIMIENTOS:\n';
            data.history.forEach((move, index) => {
                const player = data.players[move.playerId];
                const recinto = this.gameEngine.getRecintoById(move.recintoId);
                txt += `${index + 1}. ${player?.name} colocó dinosaurio en ${recinto?.name}\n`;
            });
        }
        
        const blob = new Blob([txt], { type: 'text/plain' });
        this.downloadBlob(blob, `draftosaurus_game_${Date.now()}.txt`);
    }

    downloadBlob(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        if (window.app) {
            window.app.showNotification(`Archivo ${filename} descargado`, 'success');
        }
    }

    // ===== ESTADÍSTICAS EN TIEMPO REAL =====
    generateLiveStats() {
        if (!this.gameEngine) return null;
        
        const stats = {
            currentRound: this.gameEngine.currentRound,
            currentTurn: this.gameEngine.currentTurn,
            totalMoves: this.gameEngine.gameHistory.length,
            playerStats: this.gameEngine.players.map(player => ({
                name: player.name,
                currentScore: this.gameEngine.calculateScore(player.id),
                dinosaursPlaced: Object.values(player.board).flat().length,
                favoriteEnclosure: this.getFavoriteEnclosure(player),
                completedEnclosures: this.getCompletedEnclosures(player)
            })),
            diceHistory: this.getDiceHistory(),
            topScore: Math.max(...this.gameEngine.players.map(p => this.gameEngine.calculateScore(p.id)))
        };
        
        return stats;
    }

    getFavoriteEnclosure(player) {
        const enclosureCounts = {};
        
        Object.entries(player.board).forEach(([recintoId, dinosaurs]) => {
            if (parseInt(recintoId) !== 7) { // Excluir río
                enclosureCounts[recintoId] = dinosaurs.length;
            }
        });
        
        const favoriteId = Object.keys(enclosureCounts).reduce((a, b) => 
            enclosureCounts[a] > enclosureCounts[b] ? a : b, '1');
        
        return this.gameEngine.getRecintoById(parseInt(favoriteId))?.name || 'Ninguno';
    }

    getCompletedEnclosures(player) {
        let completed = 0;
        
        Object.entries(player.board).forEach(([recintoId, dinosaurs]) => {
            const recinto = this.gameEngine.getRecintoById(parseInt(recintoId));
            if (recinto && recinto.maxCapacity && dinosaurs.length === recinto.maxCapacity) {
                completed++;
            }
        });
        
        return completed;
    }

    getDiceHistory() {
        // Extraer historial de dados de los movimientos
        const diceResults = this.gameEngine.gameHistory
            .filter(move => move.diceResult)
            .map(move => move.diceResult);
        
        return diceResults.slice(-6); // Últimos 6 dados
    }

    // ===== CLEANUP =====
    cleanup() {
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
        }
        
        if (this.backupInterval) {
            clearInterval(this.backupInterval);
        }
        
        // Guardar estado final
        this.saveGameState();
    }
}

// Extensión para el componente de estadísticas en vivo
class LiveStatsDisplay {
    constructor(container, advancedFeatures) {
        this.container = container;
        this.advancedFeatures = advancedFeatures;
        this.isVisible = false;
        this.updateInterval = null;
        
        this.createStatsPanel();
    }

    createStatsPanel() {
        const panel = document.createElement('div');
        panel.className = 'live-stats-panel';
        panel.innerHTML = `
            <div class="stats-header">
                <h3>Estadísticas en Vivo</h3>
                <button class="stats-toggle" onclick="this.parentElement.parentElement.classList.toggle('collapsed')">📊</button>
            </div>
            <div class="stats-content" id="live-stats-content">
                <!-- Content will be populated by updateStats() -->
            </div>
        `;
        
        // Agregar estilos
        if (!document.querySelector('#live-stats-styles')) {
            const style = document.createElement('style');
            style.id = 'live-stats-styles';
            style.textContent = `
                .live-stats-panel {
                    position: fixed;
                    top: 50%;
                    right: -300px;
                    width: 320px;
                    background: rgba(0, 0, 0, 0.9);
                    color: #FFF1C7;
                    border-radius: 10px 0 0 10px;
                    border: 1px solid rgba(255, 241, 199, 0.3);
                    backdrop-filter: blur(10px);
                    transition: right 0.3s ease;
                    z-index: 1000;
                    max-height: 80vh;
                    overflow-y: auto;
                }
                
                .live-stats-panel:hover,
                .live-stats-panel:not(.collapsed) {
                    right: 0;
                }
                
                .live-stats-panel.collapsed {
                    right: -280px;
                }
                
                .stats-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 15px;
                    border-bottom: 1px solid rgba(255, 241, 199, 0.2);
                    background: rgba(248, 201, 78, 0.1);
                }
                
                .stats-header h3 {
                    margin: 0;
                    font-size: 1.1rem;
                    color: #F8C94E;
                }
                
                .stats-toggle {
                    background: none;
                    border: none;
                    color: #F8C94E;
                    cursor: pointer;
                    font-size: 1.2rem;
                }
                
                .stats-content {
                    padding: 15px;
                    font-size: 0.9rem;
                }
                
                .stat-item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    padding: 5px 0;
                    border-bottom: 1px solid rgba(255, 241, 199, 0.1);
                }
                
                .stat-label {
                    color: #FFF1C7;
                }
                
                .stat-value {
                    color: #F8C94E;
                    font-weight: 600;
                }
                
                .player-stat {
                    margin: 10px 0;
                    padding: 8px;
                    background: rgba(255, 255, 255, 0.05);
                    border-radius: 5px;
                }
                
                .player-name {
                    font-weight: 600;
                    color: #F8C94E;
                    margin-bottom: 5px;
                }
                
                @media (max-width: 768px) {
                    .live-stats-panel {
                        display: none;
                    }
                }
            `;
            document.head.appendChild(style);
        }
        
        this.container.appendChild(panel);
        this.panel = panel;
    }

    show() {
        if (!this.isVisible) {
            this.isVisible = true;
            this.panel.classList.remove('collapsed');
            
            // Actualizar cada 5 segundos
            this.updateInterval = setInterval(() => {
                this.updateStats();
            }, 5000);
            
            this.updateStats();
        }
    }

    hide() {
        if (this.isVisible) {
            this.isVisible = false;
            this.panel.classList.add('collapsed');
            
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
        }
    }

    updateStats() {
        const stats = this.advancedFeatures.generateLiveStats();
        if (!stats) return;
        
        const content = document.getElementById('live-stats-content');
        if (!content) return;
        
        content.innerHTML = `
            <div class="stat-item">
                <span class="stat-label">Ronda:</span>
                <span class="stat-value">${stats.currentRound}/2</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Turno:</span>
                <span class="stat-value">${stats.currentTurn}/6</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Movimientos:</span>
                <span class="stat-value">${stats.totalMoves}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Puntuación máxima:</span>
                <span class="stat-value">${stats.topScore}</span>
            </div>
            
            <h4 style="margin: 15px 0 10px 0; color: #F8C94E;">Jugadores</h4>
            ${stats.playerStats.map(player => `
                <div class="player-stat">
                    <div class="player-name">${player.name}</div>
                    <div class="stat-item">
                        <span class="stat-label">Puntos:</span>
                        <span class="stat-value">${player.currentScore}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Dinosaurios:</span>
                        <span class="stat-value">${player.dinosaursPlaced}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Recinto favorito:</span>
                        <span class="stat-value">${player.favoriteEnclosure}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Recintos completos:</span>
                        <span class="stat-value">${player.completedEnclosures}</span>
                    </div>
                </div>
            `).join('')}
        `;
    }

    destroy() {
        this.hide();
        if (this.panel && this.panel.parentElement) {
            this.panel.parentElement.removeChild(this.panel);
        }
    }
}

// Integración con la aplicación principal
class GameAnalytics {
    constructor(gameEngine) {
        this.gameEngine = gameEngine;
        this.sessionStart = Date.now();
        this.analytics = {
            moves: [],
            timeSpent: 0,
            errors: 0,
            undos: 0,
            diceRolls: [],
            enclosureUsage: {},
            dinosaurUsage: {}
        };
    }

    trackMove(playerId, dinosaurType, recintoId, wasForced = false) {
        this.analytics.moves.push({
            timestamp: Date.now(),
            playerId,
            dinosaurType,
            recintoId,
            wasForced,
            round: this.gameEngine.currentRound,
            turn: this.gameEngine.currentTurn
        });
        
        // Actualizar contadores
        this.analytics.enclosureUsage[recintoId] = (this.analytics.enclosureUsage[recintoId] || 0) + 1;
        this.analytics.dinosaurUsage[dinosaurType] = (this.analytics.dinosaurUsage[dinosaurType] || 0) + 1;
    }

    trackDiceRoll(result) {
        this.analytics.diceRolls.push({
            timestamp: Date.now(),
            result: result.id,
            round: this.gameEngine.currentRound,
            turn: this.gameEngine.currentTurn
        });
    }

    trackError(errorType, context = {}) {
        this.analytics.errors++;
        console.log('Game error tracked:', errorType, context);
    }

    trackUndo() {
        this.analytics.undos++;
    }

    getSessionStats() {
        const sessionTime = Date.now() - this.sessionStart;
        
        return {
            ...this.analytics,
            sessionDuration: Math.floor(sessionTime / 1000), // en segundos
            averageTimePerMove: this.analytics.moves.length > 0 ? 
                Math.floor(sessionTime / this.analytics.moves.length / 1000) : 0,
            mostUsedEnclosure: this.getMostUsed(this.analytics.enclosureUsage),
            mostUsedDinosaur: this.getMostUsed(this.analytics.dinosaurUsage),
            diceDistribution: this.getDiceDistribution()
        };
    }

    getMostUsed(usage) {
        let maxKey = null;
        let maxValue = 0;
        
        Object.entries(usage).forEach(([key, value]) => {
            if (value > maxValue) {
                maxValue = value;
                maxKey = key;
            }
        });
        
        return { key: maxKey, count: maxValue };
    }

    getDiceDistribution() {
        const distribution = {};
        
        this.analytics.diceRolls.forEach(roll => {
            distribution[roll.result] = (distribution[roll.result] || 0) + 1;
        });
        
        return distribution;
    }

    async sendAnalyticsToServer() {
        try {
            const stats = this.getSessionStats();
            
            const response = await fetch('api/analytics/session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(stats)
            });
            
            if (response.ok) {
                console.log('Analytics sent successfully');
            }
        } catch (error) {
            console.warn('Failed to send analytics:', error);
        }
    }
}

// Función para inicializar todas las funcionalidades avanzadas
function initializeAdvancedFeatures(gameEngine, tableroManager) {
    const advancedFeatures = new AdvancedGameFeatures(gameEngine, tableroManager);
    const analytics = new GameAnalytics(gameEngine);
    
    // Estadísticas en vivo
    const contentContainer = document.querySelector('.content-container') || document.body;
    const liveStats = new LiveStatsDisplay(contentContainer, advancedFeatures);
    
    // Mostrar estadísticas solo en partidas digitales
    if (!gameEngine.isTrackingMode) {
        liveStats.show();
    }
    
    // Integrar analytics con el game engine
    const originalSaveMove = gameEngine.saveMove;
    gameEngine.saveMove = function(playerId, dinosaur, recintoId) {
        analytics.trackMove(playerId, dinosaur.id, recintoId);
        return originalSaveMove.call(this, playerId, dinosaur, recintoId);
    };
    
    const originalRollDice = gameEngine.rollDice;
    gameEngine.rollDice = function() {
        const result = originalRollDice.call(this);
        analytics.trackDiceRoll(result);
        return result;
    };
    
    // Enviar analytics al finalizar la partida
    const originalEndGame = gameEngine.endGame;
    gameEngine.endGame = function() {
        analytics.sendAnalyticsToServer();
        return originalEndGame.call(this);
    };
    
    // Cleanup al cerrar
    window.addEventListener('beforeunload', () => {
        advancedFeatures.cleanup();
        analytics.sendAnalyticsToServer();
        liveStats.destroy();
    });
    
    return {
        advancedFeatures,
        analytics,
        liveStats
    };
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.AdvancedGameFeatures = AdvancedGameFeatures;
    window.GameAnalytics = GameAnalytics;
    window.LiveStatsDisplay = LiveStatsDisplay;
    window.initializeAdvancedFeatures = initializeAdvancedFeatures;
}

// Exportar para Node.js si está disponible
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        AdvancedGameFeatures,
        GameAnalytics,
        LiveStatsDisplay,
        initializeAdvancedFeatures
    };
}