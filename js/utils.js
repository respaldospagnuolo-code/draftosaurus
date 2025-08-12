/**
 * Utilidades generales para Draftosaurus
 * Funciones auxiliares que se usan en toda la aplicación
 */

/**
 * Validaciones de entrada
 */
const Validators = {
    /**
     * Valida si un email es válido
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    /**
     * Valida si un nombre de usuario es válido
     */
    isValidUsername(username) {
        return username && username.length >= 3 && username.length <= 20 && /^[a-zA-Z0-9_]+$/.test(username);
    },

    /**
     * Valida si un nombre es válido
     */
    isValidName(name) {
        return name && name.trim().length >= 2 && name.trim().length <= 50;
    },

    /**
     * Valida si el número de jugadores es válido
     */
    isValidPlayerCount(count) {
        const num = parseInt(count);
        return !isNaN(num) && num >= 2 && num <= 5;
    },

    /**
     * Valida si un movimiento de dinosaurio es válido
     */
    isValidDinosaurMove(dinosaur, targetEnclosure, currentBoard) {
        if (!dinosaur || !targetEnclosure) return false;
        
        // Aquí irían las reglas específicas del juego
        return this.validateEnclosureRules(dinosaur, targetEnclosure, currentBoard);
    },

    /**
     * Valida las reglas específicas de cada recinto
     */
    validateEnclosureRules(dinosaur, enclosure, board) {
        const enclosureType = enclosure.dataset.type || enclosure.className.match(/recinto-(\w+)/)?.[1];
        const dinosaurType = dinosaur.dataset.type || dinosaur.className.match(/dinosaurio-(\w+)/)?.[1];
        
        switch (enclosureType) {
            case 'bosque-semejanza':
                return this.validateSimilarityForest(dinosaur, enclosure, board);
            case 'prado-diferencia':
                return this.validateDifferencePradow(dinosaur, enclosure, board);
            case 'pradera-amor':
                return this.validateLoveMeadow(dinosaur, enclosure, board);
            case 'trio-frondoso':
                return this.validateLeafyTrio(dinosaur, enclosure, board);
            case 'rey-selva':
                return this.validateJungleKing(dinosaur, enclosure, board);
            case 'isla-solitaria':
                return this.validateSolitaryIsland(dinosaur, enclosure, board);
            default:
                return true;
        }
    },

    /**
     * Bosque de la Semejanza - Solo dinosaurios de la misma especie
     */
    validateSimilarityForest(dinosaur, enclosure, board) {
        const existingDinosaurs = enclosure.querySelectorAll('.dinosaurio');
        if (existingDinosaurs.length === 0) return true;
        
        const dinosaurType = this.getDinosaurType(dinosaur);
        return Array.from(existingDinosaurs).every(dino => 
            this.getDinosaurType(dino) === dinosaurType
        );
    },

    /**
     * Prado de la Diferencia - Solo dinosaurios de especies distintas
     */
    validateDifferencePradow(dinosaur, enclosure, board) {
        const existingDinosaurs = enclosure.querySelectorAll('.dinosaurio');
        const dinosaurType = this.getDinosaurType(dinosaur);
        
        return !Array.from(existingDinosaurs).some(dino => 
            this.getDinosaurType(dino) === dinosaurType
        );
    },

    /**
     * Pradera del Amor - Cualquier dinosaurio
     */
    validateLoveMeadow(dinosaur, enclosure, board) {
        return true; // Sin restricciones especiales
    },

    /**
     * Trío Frondoso - Máximo 3 dinosaurios
     */
    validateLeafyTrio(dinosaur, enclosure, board) {
        const existingDinosaurs = enclosure.querySelectorAll('.dinosaurio');
        return existingDinosaurs.length < 3;
    },

    /**
     * Rey de la Selva - Solo 1 dinosaurio
     */
    validateJungleKing(dinosaur, enclosure, board) {
        const existingDinosaurs = enclosure.querySelectorAll('.dinosaurio');
        return existingDinosaurs.length === 0;
    },

    /**
     * Isla Solitaria - Solo 1 dinosaurio
     */
    validateSolitaryIsland(dinosaur, enclosure, board) {
        const existingDinosaurs = enclosure.querySelectorAll('.dinosaurio');
        return existingDinosaurs.length === 0;
    },

    /**
     * Obtiene el tipo de dinosaurio
     */
    getDinosaurType(dinosaur) {
        return dinosaur.dataset.type || 
               dinosaur.className.match(/dinosaurio-(\w+)/)?.[1] ||
               dinosaur.getAttribute('data-species');
    }
};

/**
 * Utilidades de manipulación del DOM
 */
const DOMUtils = {
    /**
     * Crea un elemento con clases y atributos
     */
    createElement(tag, classes = [], attributes = {}, content = '') {
        const element = document.createElement(tag);
        
        if (classes.length > 0) {
            element.classList.add(...classes);
        }
        
        Object.entries(attributes).forEach(([key, value]) => {
            element.setAttribute(key, value);
        });
        
        if (content) {
            element.innerHTML = content;
        }
        
        return element;
    },

    /**
     * Encuentra el ancestro más cercano con una clase específica
     */
    findAncestor(element, className) {
        while (element && !element.classList.contains(className)) {
            element = element.parentElement;
        }
        return element;
    },

    /**
     * Anima un elemento con CSS
     */
    animate(element, animationClass, duration = 1000) {
        return new Promise(resolve => {
            element.classList.add(animationClass);
            setTimeout(() => {
                element.classList.remove(animationClass);
                resolve();
            }, duration);
        });
    },

    /**
     * Muestra/oculta un elemento con animación
     */
    toggleVisible(element, show = null) {
        const isVisible = !element.classList.contains('hidden');
        const shouldShow = show !== null ? show : !isVisible;
        
        if (shouldShow && !isVisible) {
            element.classList.remove('hidden');
            element.classList.add('fade-in');
            setTimeout(() => element.classList.remove('fade-in'), 600);
        } else if (!shouldShow && isVisible) {
            element.style.animation = 'fadeIn 0.3s ease-out reverse';
            setTimeout(() => {
                element.classList.add('hidden');
                element.style.animation = '';
            }, 300);
        }
    },

    /**
     * Limpia todos los hijos de un elemento
     */
    clearChildren(element) {
        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }
    },

    /**
     * Serializa un formulario a objeto
     */
    serializeForm(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    }
};

/**
 * Utilidades de juego específicas
 */
const GameUtils = {
    /**
     * Colores de dinosaurios
     */
    DINOSAUR_COLORS: {
        't-rex': '#ff4444',
        'triceratops': '#4444ff',
        'brontosaurus': '#44ff44',
        'stegosaurus': '#ffff44',
        'pterodactyl': '#ff44ff',
        'velociraptor': '#44ffff'
    },

    /**
     * Emojis de dinosaurios
     */
    DINOSAUR_EMOJIS: {
        't-rex': '🦖',
        'triceratops': '🦕',
        'brontosaurus': '🦴',
        'stegosaurus': '🟫',
        'pterodactyl': '🦅',
        'velociraptor': '🦎'
    },

    /**
     * Crea un dinosaurio DOM
     */
    createDinosaur(type, id = null) {
        const dinosaur = DOMUtils.createElement('div', 
            ['dinosaurio', `dinosaurio-${type}`],
            {
                'data-type': type,
                'data-id': id || `dino-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
                'draggable': 'true',
                'title': this.getDinosaurName(type)
            },
            this.DINOSAUR_EMOJIS[type] || '🦕'
        );

        // Agregar eventos de drag and drop
        this.addDragEvents(dinosaur);
        
        return dinosaur;
    },

    /**
     * Obtiene el nombre legible del dinosaurio
     */
    getDinosaurName(type) {
        const names = {
            't-rex': 'Tiranosaurio Rex',
            'triceratops': 'Triceratops',
            'brontosaurus': 'Brontosaurio',
            'stegosaurus': 'Estegosaurio',
            'pterodactyl': 'Pterodáctilo',
            'velociraptor': 'Velociraptor'
        };
        return names[type] || type;
    },

    /**
     * Agrega eventos de drag and drop a un dinosaurio
     */
    addDragEvents(dinosaur) {
        dinosaur.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', dinosaur.dataset.id);
            dinosaur.classList.add('dragging');
        });

        dinosaur.addEventListener('dragend', (e) => {
            dinosaur.classList.remove('dragging');
        });
    },

    /**
     * Crea un recinto DOM
     */
    createEnclosure(type, title) {
        const enclosure = DOMUtils.createElement('div',
            ['recinto', `recinto-${type}`],
            {
                'data-type': type,
                'data-title': title
            }
        );

        // Agregar título
        const titleElement = DOMUtils.createElement('div', ['recinto-titulo'], {}, title);
        enclosure.appendChild(titleElement);

        // Agregar eventos de drop
        this.addDropEvents(enclosure);

        return enclosure;
    },

    /**
     * Agrega eventos de drop a un recinto
     */
    addDropEvents(enclosure) {
        enclosure.addEventListener('dragover', (e) => {
            e.preventDefault();
            enclosure.classList.add('valid-drop');
        });

        enclosure.addEventListener('dragleave', (e) => {
            if (!enclosure.contains(e.relatedTarget)) {
                enclosure.classList.remove('valid-drop', 'invalid-drop');
            }
        });

        enclosure.addEventListener('drop', (e) => {
            e.preventDefault();
            const dinosaurId = e.dataTransfer.getData('text/plain');
            const dinosaur = document.querySelector(`[data-id="${dinosaurId}"]`);
            
            if (dinosaur && this.canPlaceDinosaur(dinosaur, enclosure)) {
                this.placeDinosaur(dinosaur, enclosure);
                enclosure.classList.remove('valid-drop', 'invalid-drop');
            } else {
                enclosure.classList.add('invalid-drop');
                setTimeout(() => {
                    enclosure.classList.remove('invalid-drop');
                }, 1000);
            }
        });
    },

    /**
     * Verifica si se puede colocar un dinosaurio en un recinto
     */
    canPlaceDinosaur(dinosaur, enclosure) {
        return Validators.validateEnclosureRules(dinosaur, enclosure, null);
    },

    /**
     * Coloca un dinosaurio en un recinto
     */
    placeDinosaur(dinosaur, enclosure) {
        // Remover de la posición anterior
        if (dinosaur.parentElement) {
            dinosaur.parentElement.removeChild(dinosaur);
        }
        
        // Agregar al nuevo recinto
        enclosure.appendChild(dinosaur);
        
        // Animación de colocación
        DOMUtils.animate(dinosaur, 'bounce', 600);
        
        // Disparar evento personalizado
        document.dispatchEvent(new CustomEvent('dinosaurPlaced', {
            detail: {
                dinosaur: dinosaur,
                enclosure: enclosure,
                dinosaurType: dinosaur.dataset.type,
                enclosureType: enclosure.dataset.type
            }
        }));
    },

    /**
     * Calcula puntos de un recinto
     */
    calculateEnclosurePoints(enclosure) {
        const enclosureType = enclosure.dataset.type;
        const dinosaurs = enclosure.querySelectorAll('.dinosaurio');
        
        switch (enclosureType) {
            case 'bosque-semejanza':
                return this.calculateSimilarityPoints(dinosaurs);
            case 'prado-diferencia':
                return this.calculateDifferencePoints(dinosaurs);
            case 'pradera-amor':
                return this.calculateLovePoints(dinosaurs);
            case 'trio-frondoso':
                return this.calculateTrioPoints(dinosaurs);
            case 'rey-selva':
                return this.calculateKingPoints(dinosaurs);
            case 'isla-solitaria':
                return this.calculateIslandPoints(dinosaurs);
            default:
                return 0;
        }
    },

    /**
     * Calcula puntos del Bosque de la Semejanza
     */
    calculateSimilarityPoints(dinosaurs) {
        const count = dinosaurs.length;
        const pointsTable = [0, 1, 3, 6, 10, 15, 21];
        return pointsTable[Math.min(count, 6)] || 0;
    },

    /**
     * Calcula puntos del Prado de la Diferencia
     */
    calculateDifferencePoints(dinosaurs) {
        const count = dinosaurs.length;
        const pointsTable = [0, 1, 3, 6, 10, 15, 21];
        return pointsTable[Math.min(count, 6)] || 0;
    },

    /**
     * Calcula puntos de la Pradera del Amor
     */
    calculateLovePoints(dinosaurs) {
        const typeCount = {};
        Array.from(dinosaurs).forEach(dino => {
            const type = Validators.getDinosaurType(dino);
            typeCount[type] = (typeCount[type] || 0) + 1;
        });
        
        let points = 0;
        Object.values(typeCount).forEach(count => {
            points += Math.floor(count / 2) * 5; // 5 puntos por pareja
        });
        
        return points;
    },

    /**
     * Calcula puntos del Trío Frondoso
     */
    calculateTrioPoints(dinosaurs) {
        return dinosaurs.length === 3 ? 7 : 0;
    },

    /**
     * Calcula puntos del Rey de la Selva
     */
    calculateKingPoints(dinosaurs) {
        // Esto requiere comparar con otros jugadores
        // Por ahora devuelve 7 si hay exactamente 1 dinosaurio
        return dinosaurs.length === 1 ? 7 : 0;
    },

    /**
     * Calcula puntos de la Isla Solitaria
     */
    calculateIslandPoints(dinosaurs) {
        if (dinosaurs.length !== 1) return 0;
        
        // Verificar si es el único de su especie en el parque
        // Esto requiere acceso al tablero completo
        const dinosaur = dinosaurs[0];
        const type = Validators.getDinosaurType(dinosaur);
        const board = DOMUtils.findAncestor(dinosaur, 'tablero');
        
        if (board) {
            const allDinosaurs = board.querySelectorAll('.dinosaurio');
            const sameTypeCount = Array.from(allDinosaurs).filter(dino => 
                Validators.getDinosaurType(dino) === type
            ).length;
            
            return sameTypeCount === 1 ? 7 : 0;
        }
        
        return 0;
    },

    /**
     * Genera una mano aleatoria de dinosaurios
     */
    generateRandomHand(count = 6) {
        const types = Object.keys(this.DINOSAUR_COLORS);
        const hand = [];
        
        for (let i = 0; i < count; i++) {
            const randomType = types[Math.floor(Math.random() * types.length)];
            hand.push(this.createDinosaur(randomType));
        }
        
        return hand;
    },

    /**
     * Simula el lanzamiento del dado
     */
    rollDice() {
        const diceOptions = [
            'left', 'right', 'forest', 'rocks', 'empty', 'no-trex'
        ];
        
        return diceOptions[Math.floor(Math.random() * diceOptions.length)];
    },

    /**
     * Obtiene la restricción del dado en formato legible
     */
    getDiceRestrictionText(diceResult) {
        const restrictions = {
            'left': 'Zona izquierda del parque',
            'right': 'Zona derecha del parque',
            'forest': 'Zona boscosa',
            'rocks': 'Zona de rocas',
            'empty': 'Recinto vacío',
            'no-trex': 'Recinto sin T-Rex'
        };
        
        return restrictions[diceResult] || 'Sin restricción';
    },

    /**
     * Valida restricción del dado
     */
    validateDiceRestriction(enclosure, diceResult, board) {
        if (!diceResult) return true;
        
        switch (diceResult) {
            case 'left':
                return this.isLeftSide(enclosure, board);
            case 'right':
                return this.isRightSide(enclosure, board);
            case 'forest':
                return this.isForestZone(enclosure);
            case 'rocks':
                return this.isRockZone(enclosure);
            case 'empty':
                return this.isEmpty(enclosure);
            case 'no-trex':
                return this.hasNoTRex(enclosure);
            default:
                return true;
        }
    },

    /**
     * Verifica si el recinto está en el lado izquierdo
     */
    isLeftSide(enclosure, board) {
        const enclosures = Array.from(board.querySelectorAll('.recinto'));
        const index = enclosures.indexOf(enclosure);
        return index % 3 === 0; // Primera columna en grid 3x2
    },

    /**
     * Verifica si el recinto está en el lado derecho
     */
    isRightSide(enclosure, board) {
        const enclosures = Array.from(board.querySelectorAll('.recinto'));
        const index = enclosures.indexOf(enclosure);
        return index % 3 === 2; // Tercera columna en grid 3x2
    },

    /**
     * Verifica si el recinto es zona boscosa
     */
    isForestZone(enclosure) {
        return enclosure.classList.contains('forest-zone') || 
               enclosure.dataset.zone === 'forest';
    },

    /**
     * Verifica si el recinto es zona de rocas
     */
    isRockZone(enclosure) {
        return enclosure.classList.contains('rock-zone') || 
               enclosure.dataset.zone === 'rocks';
    },

    /**
     * Verifica si el recinto está vacío
     */
    isEmpty(enclosure) {
        return enclosure.querySelectorAll('.dinosaurio').length === 0;
    },

    /**
     * Verifica si el recinto no tiene T-Rex
     */
    hasNoTRex(enclosure) {
        const dinosaurs = enclosure.querySelectorAll('.dinosaurio');
        return !Array.from(dinosaurs).some(dino => 
            Validators.getDinosaurType(dino) === 't-rex'
        );
    }
};

/**
 * Utilidades de comunicación con el servidor
 */
const APIUtils = {
    /**
     * URL base de la API
     */
    BASE_URL: 'php/api/',

    /**
     * Realiza una petición GET
     */
    async get(endpoint, params = {}) {
        const url = new URL(this.BASE_URL + endpoint, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            url.searchParams.append(key, value);
        });

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        return this.handleResponse(response);
    },

    /**
     * Realiza una petición POST
     */
    async post(endpoint, data = {}) {
        const response = await fetch(this.BASE_URL + endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        return this.handleResponse(response);
    },

    /**
     * Realiza una petición PUT
     */
    async put(endpoint, data = {}) {
        const response = await fetch(this.BASE_URL + endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        return this.handleResponse(response);
    },

    /**
     * Realiza una petición DELETE
     */
    async delete(endpoint) {
        const response = await fetch(this.BASE_URL + endpoint, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        return this.handleResponse(response);
    },

    /**
     * Maneja la respuesta del servidor
     */
    async handleResponse(response) {
        if (!response.ok) {
            const error = await response.text();
            throw new Error(`HTTP ${response.status}: ${error}`);
        }

        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        }

        return await response.text();
    },

    /**
     * Sube un archivo
     */
    async uploadFile(endpoint, file, additionalData = {}) {
        const formData = new FormData();
        formData.append('file', file);
        
        Object.entries(additionalData).forEach(([key, value]) => {
            formData.append(key, value);
        });

        const response = await fetch(this.BASE_URL + endpoint, {
            method: 'POST',
            body: formData
        });

        return this.handleResponse(response);
    }
};

/**
 * Utilidades de estado del juego
 */
const GameStateUtils = {
    /**
     * Crea un estado inicial del juego
     */
    createInitialState(players, gameMode = 'tracking') {
        return {
            id: this.generateGameId(),
            mode: gameMode,
            status: 'waiting', // waiting, playing, finished
            players: players.map((name, index) => ({
                id: index + 1,
                name: name,
                board: this.createEmptyBoard(),
                score: 0,
                isActive: index === 0
            })),
            currentRound: 1,
            currentTurn: 1,
            maxRounds: 2,
            turnsPerRound: 6,
            diceResult: null,
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };
    },

    /**
     * Crea un tablero vacío
     */
    createEmptyBoard() {
        return {
            'bosque-semejanza': [],
            'prado-diferencia': [],
            'pradera-amor': [],
            'trio-frondoso': [],
            'rey-selva': [],
            'isla-solitaria': [],
            'rio': []
        };
    },

    /**
     * Genera un ID único para la partida
     */
    generateGameId() {
        return 'game-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    },

    /**
     * Valida un estado de juego
     */
    validateGameState(state) {
        if (!state || typeof state !== 'object') return false;
        if (!state.id || !state.players || !Array.isArray(state.players)) return false;
        if (state.players.length < 2 || state.players.length > 5) return false;
        if (!['waiting', 'playing', 'finished'].includes(state.status)) return false;
        if (!['tracking', 'digital'].includes(state.mode)) return false;
        
        return true;
    },

    /**
     * Clona un estado de juego
     */
    cloneGameState(state) {
        return JSON.parse(JSON.stringify(state));
    },

    /**
     * Actualiza el estado del juego
     */
    updateGameState(currentState, updates) {
        const newState = this.cloneGameState(currentState);
        Object.assign(newState, updates);
        newState.updatedAt = new Date().toISOString();
        return newState;
    },

    /**
     * Obtiene el jugador activo
     */
    getActivePlayer(state) {
        return state.players.find(player => player.isActive);
    },

    /**
     * Cambia al siguiente jugador
     */
    nextPlayer(state) {
        const currentPlayerIndex = state.players.findIndex(player => player.isActive);
        const nextPlayerIndex = (currentPlayerIndex + 1) % state.players.length;
        
        const newState = this.cloneGameState(state);
        newState.players.forEach((player, index) => {
            player.isActive = index === nextPlayerIndex;
        });
        
        return newState;
    },

    /**
     * Avanza al siguiente turno
     */
    nextTurn(state) {
        const newState = this.nextPlayer(state);
        
        if (newState.currentTurn >= newState.turnsPerRound) {
            newState.currentTurn = 1;
            newState.currentRound++;
            
            if (newState.currentRound > newState.maxRounds) {
                newState.status = 'finished';
                newState.players.forEach(player => {
                    player.score = this.calculatePlayerScore(player.board);
                });
            }
        } else {
            newState.currentTurn++;
        }
        
        return newState;
    },

    /**
     * Calcula la puntuación de un jugador
     */
    calculatePlayerScore(board) {
        let totalScore = 0;
        
        // Puntos de recintos
        Object.entries(board).forEach(([enclosureType, dinosaurs]) => {
            if (enclosureType === 'rio') {
                totalScore += dinosaurs.length; // 1 punto por dinosaurio en el río
            } else {
                // Simular cálculo de puntos por recinto
                totalScore += this.calculateEnclosureScore(enclosureType, dinosaurs, board);
            }
        });
        
        // Puntos bonus por T-Rex
        totalScore += this.calculateTRexBonus(board);
        
        return totalScore;
    },

    /**
     * Calcula puntos de un recinto específico
     */
    calculateEnclosureScore(enclosureType, dinosaurs, fullBoard) {
        // Esta es una versión simplificada
        // En la implementación real, usar GameUtils.calculateEnclosurePoints
        switch (enclosureType) {
            case 'bosque-semejanza':
            case 'prado-diferencia':
                const pointsTable = [0, 1, 3, 6, 10, 15, 21];
                return pointsTable[Math.min(dinosaurs.length, 6)] || 0;
            case 'pradera-amor':
                return Math.floor(dinosaurs.length / 2) * 5;
            case 'trio-frondoso':
                return dinosaurs.length === 3 ? 7 : 0;
            case 'rey-selva':
            case 'isla-solitaria':
                return dinosaurs.length === 1 ? 7 : 0;
            default:
                return 0;
        }
    },

    /**
     * Calcula bonus por T-Rex
     */
    calculateTRexBonus(board) {
        let bonus = 0;
        Object.entries(board).forEach(([enclosureType, dinosaurs]) => {
            if (enclosureType !== 'rio' && dinosaurs.some(dino => dino.type === 't-rex')) {
                bonus += 1;
            }
        });
        return bonus;
    }
};

/**
 * Utilidades de sonido (opcional)
 */
const SoundUtils = {
    /**
     * Contexto de audio
     */
    audioContext: null,

    /**
     * Inicializa el contexto de audio
     */
    init() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('Audio no soportado:', e);
        }
    },

    /**
     * Reproduce un sonido simple
     */
    playTone(frequency = 440, duration = 200, type = 'sine') {
        if (!this.audioContext) return;

        const oscillator = this.audioContext.createOscillator();
        const gainNode = this.audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(this.audioContext.destination);

        oscillator.frequency.value = frequency;
        oscillator.type = type;

        gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + duration / 1000);

        oscillator.start(this.audioContext.currentTime);
        oscillator.stop(this.audioContext.currentTime + duration / 1000);
    },

    /**
     * Sonido de colocación exitosa
     */
    playSuccess() {
        this.playTone(523, 150); // Do
        setTimeout(() => this.playTone(659, 150), 100); // Mi
    },

    /**
     * Sonido de error
     */
    playError() {
        this.playTone(220, 300, 'square');
    },

    /**
     * Sonido de dado
     */
    playDiceRoll() {
        for (let i = 0; i < 5; i++) {
            setTimeout(() => {
                this.playTone(Math.random() * 400 + 200, 50);
            }, i * 50);
        }
    }
};

// Exportar utilidades para uso global
window.Validators = Validators;
window.DOMUtils = DOMUtils;
window.GameUtils = GameUtils;
window.APIUtils = APIUtils;
window.GameStateUtils = GameStateUtils;
window.SoundUtils = SoundUtils;