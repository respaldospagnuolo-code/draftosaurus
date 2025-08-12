/**
 * Game Engine para Draftosaurus
 * Maneja toda la lógica del juego, reglas y mecánicas
 */

class GameEngine {
    constructor() {
        this.game = null;
        this.players = [];
        this.currentPlayer = 0;
        this.currentRound = 1;
        this.currentTurn = 1;
        this.dinosaurBag = [];
        this.currentDiceRestriction = null;
        this.gameHistory = [];
        this.isTrackingMode = false;
        
        this.initializeDinosaurs();
        this.initializeRecintos();
    }

    // ===== INICIALIZACIÓN =====
    initializeDinosaurs() {
        this.dinosaurTypes = [
            { id: 1, name: 'T-Rex', color: 'verde', icon: 'img/dinosaurios/dino-1-perfil.png', boardIcon: 'img/dinosaurios/dino-1-arriba.png', cssClass: 'verde' },
            { id: 2, name: 'Triceratops', color: 'azul', icon: 'img/dinosaurios/dino-2-perfil.png', boardIcon: 'img/dinosaurios/dino-2-arriba.png', cssClass: 'azul' },
            { id: 3, name: 'Brontosaurus', color: 'amarillo', icon: 'img/dinosaurios/dino-3-perfil.png', boardIcon: 'img/dinosaurios/dino-3-arriba.png', cssClass: 'amarillo' },
            { id: 4, name: 'Stegosaurus', color: 'rojo', icon: 'img/dinosaurios/dino-4-perfil.png', boardIcon: 'img/dinosaurios/dino-4-arriba.png', cssClass: 'rojo' },
            { id: 5, name: 'Pteranodon', color: 'naranja', icon: 'img/dinosaurios/dino-5-perfil.png', boardIcon: 'img/dinosaurios/dino-5-arriba.png', cssClass: 'naranja' },
            { id: 6, name: 'Parasaurolophus', color: 'rosa', icon: 'img/dinosaurios/dino-6-perfil.png', boardIcon: 'img/dinosaurios/dino-6-arriba.png', cssClass: 'rosa' }
        ];

        this.refreshDinosaurBag();
    }

    refreshDinosaurBag() {
        this.dinosaurBag = [];
        this.dinosaurTypes.forEach(type => {
            for (let i = 0; i < 10; i++) {
                this.dinosaurBag.push({ ...type, uniqueId: `${type.id}-${i}-${Date.now()}` });
            }
        });
    }

    initializeRecintos() {
        this.recintos = [
            {
                id: 1,
                name: 'Bosque de la Semejanza',
                description: 'Solo puede albergar dinosaurios de la misma especie',
                maxCapacity: 6,
                zone: 'izquierda',
                placementRule: 'same_species',
                scoringRule: 'by_count'
            },
            {
                id: 2,
                name: 'Prado de la Diferencia',
                description: 'Solo puede albergar dinosaurios de especies distintas',
                maxCapacity: 6,
                zone: 'izquierda',
                placementRule: 'different_species',
                scoringRule: 'by_count'
            },
            {
                id: 3,
                name: 'Pradera del Amor',
                description: 'Puede albergar dinosaurios de todas las especies',
                maxCapacity: 6,
                zone: 'izquierda',
                placementRule: 'any',
                scoringRule: 'pairs'
            },
            {
                id: 4,
                name: 'Trío Frondoso',
                description: 'Puede albergar hasta 3 dinosaurios',
                maxCapacity: 3,
                zone: 'derecha',
                placementRule: 'any',
                scoringRule: 'exactly_three'
            },
            {
                id: 5,
                name: 'Rey de la Selva',
                description: 'Puede albergar solo 1 dinosaurio',
                maxCapacity: 1,
                zone: 'derecha',
                placementRule: 'any',
                scoringRule: 'majority'
            },
            {
                id: 6,
                name: 'Isla Solitaria',
                description: 'Puede albergar solo 1 dinosaurio',
                maxCapacity: 1,
                zone: 'derecha',
                placementRule: 'any',
                scoringRule: 'unique_in_park'
            },
            {
                id: 7,
                name: 'Río',
                description: 'Recinto especial para dinosaurios que no pueden colocarse',
                maxCapacity: null,
                zone: 'rio',
                placementRule: 'any',
                scoringRule: 'one_per_dino'
            }
        ];
    }

    // ===== GESTIÓN DEL JUEGO =====
    initGame(gameData) {
        this.game = gameData;
        this.isTrackingMode = gameData.mode === 'seguimiento';
        this.setupPlayers();
        
        if (!this.isTrackingMode) {
            this.shuffleDinosaurs();
            this.startRound();
        }
    }

    initTrackingMode(numPlayers) {
        this.isTrackingMode = true;
        this.players = [];
        
        for (let i = 0; i < numPlayers; i++) {
            this.players.push({
                id: i,
                name: `Jugador ${i + 1}`,
                board: this.createEmptyBoard(),
                hand: [],
                score: 0
            });
        }
    }

    setupPlayers() {
        this.players = [];
        for (let i = 0; i < this.game.numPlayers; i++) {
            this.players.push({
                id: i,
                name: `Jugador ${i + 1}`,
                board: this.createEmptyBoard(),
                hand: [],
                score: 0
            });
        }
    }

    createEmptyBoard() {
        const board = {};
        this.recintos.forEach(recinto => {
            board[recinto.id] = [];
        });
        return board;
    }

    shuffleDinosaurs() {
        for (let i = this.dinosaurBag.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.dinosaurBag[i], this.dinosaurBag[j]] = [this.dinosaurBag[j], this.dinosaurBag[i]];
        }
    }

    // ===== LÓGICA DE TURNOS =====
    startRound() {
        this.currentTurn = 1;
        this.dealDinosaurs();
        this.startTurn();
    }

    dealDinosaurs() {
        this.players.forEach(player => {
            player.hand = [];
            for (let i = 0; i < 6; i++) {
                if (this.dinosaurBag.length > 0) {
                    player.hand.push(this.dinosaurBag.pop());
                }
            }
        });
    }

    startTurn() {
        if (this.currentTurn > 6) {
            this.endRound();
            return;
        }

        this.currentDiceRestriction = null;
    }

    rollDice() {
        const diceResults = [
            { 
                id: 'left_zone', 
                description: 'Zona izquierda del parque',
                icon: 'img/dados/cara-dado-1.png'
            },
            { 
                id: 'right_zone', 
                description: 'Zona derecha del parque',
                icon: 'img/dados/cara-dado-2.png'
            },
            { 
                id: 'forest_zone', 
                description: 'Zona boscosa',
                icon: 'img/dados/cara-dado-3.png'
            },
            { 
                id: 'rocky_zone', 
                description: 'Zona rocosa',
                icon: 'img/dados/cara-dado-4.png'
            },
            { 
                id: 'empty_enclosure', 
                description: 'Recinto vacío',
                icon: 'img/dados/cara-dado-5.png'
            },
            { 
                id: 'no_trex', 
                description: 'Recinto sin T-Rex',
                icon: 'img/dados/cara-dado-6.png'
            }
        ];

        const result = diceResults[Math.floor(Math.random() * diceResults.length)];
        this.currentDiceRestriction = result;
        
        return result;
    }

    // ===== COLOCACIÓN DE DINOSAURIOS =====
    canPlaceDinosaur(playerId, dinosaur, recintoId) {
        const player = this.players[playerId];
        const recinto = this.recintos.find(r => r.id === recintoId);
        const currentDinosaurs = player.board[recintoId];

        // Verificar restricción del dado (excepto para el jugador activo)
        if (!this.isTrackingMode && playerId !== this.currentPlayer && this.currentDiceRestriction) {
            if (!this.checkDiceRestriction(recintoId, currentDinosaurs, dinosaur)) {
                return false;
            }
        }

        // Verificar capacidad del recinto
        if (recinto.maxCapacity && currentDinosaurs.length >= recinto.maxCapacity) {
            return false;
        }

        // Verificar reglas específicas del recinto
        return this.checkRecintoRules(recinto, currentDinosaurs, dinosaur);
    }

    checkDiceRestriction(recintoId, currentDinosaurs, dinosaur) {
        const recinto = this.recintos.find(r => r.id === recintoId);
        
        switch (this.currentDiceRestriction.id) {
            case 'left_zone':
                return recinto.zone === 'izquierda';
            case 'right_zone':
                return recinto.zone === 'derecha';
            case 'forest_zone':
                return [1, 2, 3].includes(recintoId);
            case 'rocky_zone':
                return [4, 5, 6].includes(recintoId);
            case 'empty_enclosure':
                return currentDinosaurs.length === 0;
            case 'no_trex':
                return !currentDinosaurs.some(dino => dino.name === 'T-Rex');
            default:
                return true;
        }
    }

    checkRecintoRules(recinto, currentDinosaurs, dinosaur) {
        switch (recinto.placementRule) {
            case 'same_species':
                return currentDinosaurs.length === 0 || 
                       currentDinosaurs.every(dino => dino.name === dinosaur.name);
            case 'different_species':
                return !currentDinosaurs.some(dino => dino.name === dinosaur.name);
            case 'any':
                return true;
            default:
                return true;
        }
    }

    placeDinosaur(playerId, dinosaur, recintoId) {
        const success = this.canPlaceDinosaur(playerId, dinosaur, recintoId);
        let forcedToRiver = false;
        
        if (!success && recintoId !== 7) {
            // Si no se puede colocar, va al río
            recintoId = 7;
            forcedToRiver = true;
        }

        const player = this.players[playerId];
        
        // Remover de la mano (solo en modo digital)
        if (!this.isTrackingMode) {
            const handIndex = player.hand.findIndex(dino => dino.uniqueId === dinosaur.uniqueId);
            if (handIndex !== -1) {
                player.hand.splice(handIndex, 1);
            }
        }

        // Añadir al tablero
        const dinosaurData = {
            ...dinosaur,
            round: this.currentRound,
            turn: this.currentTurn
        };
        
        player.board[recintoId].push(dinosaurData);

        // Guardar el movimiento
        this.saveMove(playerId, dinosaur, recintoId);
        
        return { success: true, forcedToRiver, finalRecinto: recintoId };
    }

    // Método específico para modo seguimiento
    addDinosaurTracking(playerId, dinosaurType, recintoId) {
        const dinosaur = this.dinosaurTypes.find(d => d.id === dinosaurType);
        if (!dinosaur) return false;

        const tempDinosaur = { 
            ...dinosaur, 
            uniqueId: `tracking-${Date.now()}-${Math.random()}` 
        };
        
        return this.placeDinosaur(playerId, tempDinosaur, recintoId).success;
    }

    saveMove(playerId, dinosaur, recintoId) {
        const moveData = {
            playerId,
            dinosaur: dinosaur.uniqueId,
            dinosaurType: dinosaur.id,
            recintoId,
            round: this.currentRound,
            turn: this.currentTurn,
            timestamp: new Date().toISOString()
        };

        this.gameHistory.push(moveData);

        // Enviar al servidor solo en modo digital
        if (!this.isTrackingMode) {
            this.sendMoveToServer(moveData);
        }
    }

    async sendMoveToServer(moveData) {
        try {
            const response = await fetch('api/game/move.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(moveData)
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('Error enviando movimiento:', error);
        }
    }

    // ===== FINALIZACIÓN DE TURNOS =====
    nextPlayer() {
        this.currentPlayer = (this.currentPlayer + 1) % this.players.length;
    }

    passDinosaurs() {
        const hands = this.players.map(player => player.hand);
        const firstHand = hands[0];
        
        for (let i = 0; i < hands.length - 1; i++) {
            this.players[i].hand = hands[i + 1];
        }
        this.players[this.players.length - 1].hand = firstHand;
    }

    endTurn() {
        if (this.isTrackingMode) return;
        
        this.passDinosaurs();
        this.nextPlayer();
        this.currentTurn++;
        
        if (this.currentTurn <= 6) {
            this.startTurn();
        } else {
            this.endRound();
        }
    }

    endRound() {
        if (this.currentRound < 2) {
            this.currentRound++;
            this.startRound();
        } else {
            this.endGame();
        }
    }

    // ===== PUNTUACIÓN =====
    calculateScore(playerId) {
        const player = this.players[playerId];
        let totalScore = 0;

        // Calcular puntos por cada recinto
        this.recintos.forEach(recinto => {
            const dinosaurs = player.board[recinto.id];
            const recintoScore = this.calculateRecintoScore(recinto, dinosaurs, playerId);
            totalScore += recintoScore;
        });

        // Bonus por T-Rex
        totalScore += this.calculateTRexBonus(player.board);

        player.score = totalScore;
        return totalScore;
    }

    calculateAllScores() {
        const scores = [];
        this.players.forEach((player, index) => {
            scores[index] = this.calculateScore(index);
        });
        return scores;
    }

    calculateRecintoScore(recinto, dinosaurs, playerId) {
        if (dinosaurs.length === 0) return 0;

        switch (recinto.scoringRule) {
            case 'by_count':
                return this.getScoreByCount(dinosaurs.length);
            case 'pairs':
                return this.calculatePairsScore(dinosaurs);
            case 'exactly_three':
                return dinosaurs.length === 3 ? 7 : 0;
            case 'majority':
                return this.calculateMajorityScore(dinosaurs[0], playerId);
            case 'unique_in_park':
                return this.calculateUniqueScore(dinosaurs[0], playerId);
            case 'one_per_dino':
                return dinosaurs.length;
            default:
                return 0;
        }
    }

    getScoreByCount(count) {
        const scores = [0, 1, 3, 6, 10, 15, 21];
        return scores[count] || 21;
    }

    calculatePairsScore(dinosaurs) {
        const species = {};
        dinosaurs.forEach(dino => {
            species[dino.name] = (species[dino.name] || 0) + 1;
        });
        
        let pairs = 0;
        Object.values(species).forEach(count => {
            pairs += Math.floor(count / 2);
        });
        
        return pairs * 5;
    }

    calculateMajorityScore(dinosaur, playerId) {
        if (!dinosaur) return 0;
        
        const currentPlayerCount = this.countDinosaurInPark(playerId, dinosaur.name);
        let hasMore = true;
        
        // Verificar contra otros jugadores
        for (let i = 0; i < this.players.length; i++) {
            if (i !== playerId) {
                const otherCount = this.countDinosaurInPark(i, dinosaur.name);
                if (otherCount >= currentPlayerCount) {
                    hasMore = false;
                    break;
                }
            }
        }
        
        return hasMore ? 7 : 0;
    }

    calculateUniqueScore(dinosaur, playerId) {
        if (!dinosaur) return 0;
        
        const totalCount = this.countDinosaurInPark(playerId, dinosaur.name);
        return totalCount === 1 ? 7 : 0;
    }

    countDinosaurInPark(playerId, dinosaurName) {
        const player = this.players[playerId];
        let count = 0;
        
        Object.values(player.board).forEach(recinto => {
            count += recinto.filter(dino => dino.name === dinosaurName).length;
        });
        
        return count;
    }

    calculateTRexBonus(board) {
        let bonus = 0;
        
        // +1 punto por cada recinto (excepto río) que contenga al menos un T-Rex
        this.recintos.forEach(recinto => {
            if (recinto.id !== 7) { // No incluir río
                const dinosaurs = board[recinto.id];
                if (dinosaurs.some(dino => dino.name === 'T-Rex')) {
                    bonus += 1;
                }
            }
        });
        
        return bonus;
    }

    // ===== FINALIZACIÓN DEL JUEGO =====
    endGame() {
        // Calcular puntuaciones finales
        this.players.forEach((player, index) => {
            player.score = this.calculateScore(index);
        });

        // Determinar ganador
        const winner = this.players.reduce((prev, current) => 
            current.score > prev.score ? current : prev
        );

        return {
            winner: winner,
            scores: this.players.map(p => ({ id: p.id, name: p.name, score: p.score })),
            gameHistory: this.gameHistory
        };
    }

    pauseGame() {
        // Implementar lógica de pausa si es necesario
        console.log('Juego pausado');
    }

    // ===== ESTADO DEL JUEGO =====
    getGameState() {
        return {
            game: this.game,
            players: this.players,
            currentPlayer: this.currentPlayer,
            currentRound: this.currentRound,
            currentTurn: this.currentTurn,
            currentDiceRestriction: this.currentDiceRestriction,
            gameHistory: this.gameHistory,
            isTrackingMode: this.isTrackingMode
        };
    }

    loadGameState(state) {
        this.game = state.game;
        this.players = state.players;
        this.currentPlayer = state.currentPlayer;
        this.currentRound = state.currentRound;
        this.currentTurn = state.currentTurn;
        this.currentDiceRestriction = state.currentDiceRestriction;
        this.gameHistory = state.gameHistory || [];
        this.isTrackingMode = state.isTrackingMode || false;
    }

    // ===== UTILIDADES =====
    getDinosaurTypeById(id) {
        return this.dinosaurTypes.find(type => type.id === id);
    }

    getRecintoById(id) {
        return this.recintos.find(recinto => recinto.id === id);
    }

    validateMove(playerId, dinosaurType, recintoId) {
        const dinosaur = this.getDinosaurTypeById(dinosaurType);
        if (!dinosaur) {
            return { valid: false, message: 'Tipo de dinosaurio no válido' };
        }

        const recinto = this.getRecintoById(recintoId);
        if (!recinto) {
            return { valid: false, message: 'Recinto no válido' };
        }

        if (!this.canPlaceDinosaur(playerId, dinosaur, recintoId)) {
            return { valid: false, message: 'No se puede colocar el dinosaurio en este recinto' };
        }

        return { valid: true };
    }

    // ===== DEBUG HELPERS =====
    debugPrintBoard(playerId) {
        const player = this.players[playerId];
        console.log(`Tablero de ${player.name}:`);
        
        this.recintos.forEach(recinto => {
            const dinosaurs = player.board[recinto.id];
            console.log(`  ${recinto.name}: ${dinosaurs.length} dinosaurios`);
            dinosaurs.forEach(dino => {
                console.log(`    - ${dino.name} (${dino.color})`);
            });
        });
        
        console.log(`Puntuación: ${player.score || 'No calculada'}`);
    }

    debugPrintGameState() {
        console.log('Estado del juego:');
        console.log(`  Ronda: ${this.currentRound}/2`);
        console.log(`  Turno: ${this.currentTurn}/6`);
        console.log(`  Jugador activo: ${this.currentPlayer}`);
        console.log(`  Restricción dado: ${this.currentDiceRestriction?.description || 'Ninguna'}`);
        console.log(`  Modo: ${this.isTrackingMode ? 'Seguimiento' : 'Digital'}`);
    }
}

// Hacer disponible globalmente
if (typeof window !== 'undefined') {
    window.GameEngine = GameEngine;
}

// Exportar para Node.js si está disponible
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GameEngine;
}