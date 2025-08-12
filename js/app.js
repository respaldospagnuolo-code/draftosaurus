/**
 * Draftosaurus I.G.P.D. - Aplicación Principal Unificada
 * Sistema Informático de Gestión de Partidas para Draftosaurus
 */

// Estado global de la aplicación
const AppState = {
    currentScreen: 'main-screen',
    currentBackground: 'main',
    user: null,
    gameData: null,
    isLoading: false,
    trackingData: null
};

// Configuración de la aplicación
const AppConfig = {
    API_BASE_URL: 'api/',
    ANIMATION_DURATION: 400,
    BACKGROUND_TRANSITION_DURATION: 800
};

// Clase principal de la aplicación
class DraftosaurusApp {
    constructor() {
        this.gameEngine = null;
        this.tableroManager = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadInitialData();
        this.initializeGameComponents();
        console.log('Draftosaurus App initialized');
    }

    // Inicializar componentes del juego
    initializeGameComponents() {
        // Inicializar motor del juego si está disponible
        if (typeof GameEngine !== 'undefined') {
            this.gameEngine = new GameEngine();
        }
        
        // Inicializar manager del tablero si está disponible
        if (typeof TableroManager !== 'undefined' && this.gameEngine) {
            this.tableroManager = new TableroManager(this.gameEngine);
        }
    }

    // Configurar event listeners
    setupEventListeners() {
        // Botón de regreso
        document.getElementById('back-button')?.addEventListener('click', () => this.showMain());
        
        // Formularios
        this.setupFormListeners();
        
        // Navegación con teclado
        document.addEventListener('keydown', (e) => this.handleKeyNavigation(e));
        
        // Prevenir zoom en dispositivos móviles
        document.addEventListener('touchstart', (e) => {
            if (e.touches.length > 1) {
                e.preventDefault();
            }
        });

        // Manejar cambios de orientación
        window.addEventListener('orientationchange', () => {
            setTimeout(() => this.handleOrientationChange(), 100);
        });
    }

    setupFormListeners() {
        // Formulario de nueva partida
        const newGameForm = document.getElementById('new-game-form');
        if (newGameForm) {
            newGameForm.addEventListener('submit', (e) => this.handleNewGameSubmit(e));
        }
        
        // Formulario de login
        const loginForm = document.getElementById('login-form-element');
        if (loginForm) {
            loginForm.addEventListener('submit', (e) => this.handleLoginSubmit(e));
        }
        
        // Formulario de registro
        const registerForm = document.getElementById('register-form-element');
        if (registerForm) {
            registerForm.addEventListener('submit', (e) => this.handleRegisterSubmit(e));
        }
    }

    // Cargar datos iniciales
    async loadInitialData() {
        try {
            // Verificar si hay una sesión activa
            const sessionData = await this.checkSession();
            if (sessionData && sessionData.success && sessionData.data) {
                AppState.user = sessionData.data;
                this.updateUIForLoggedUser();
            }
        } catch (error) {
            console.log('No active session found');
        }
    }

    // ===== GESTIÓN DE FONDOS =====
    changeBackground(backgroundName) {
        if (AppState.currentBackground === backgroundName) return Promise.resolve();
        
        return new Promise((resolve) => {
            // Ocultar fondo actual
            const currentBg = document.getElementById(`bg-${AppState.currentBackground}`);
            if (currentBg) {
                currentBg.classList.remove('active');
            }
            
            // Mostrar nuevo fondo después de un breve delay
            setTimeout(() => {
                const newBg = document.getElementById(`bg-${backgroundName}`);
                if (newBg) {
                    newBg.classList.add('active');
                }
                AppState.currentBackground = backgroundName;
                resolve();
            }, 100);
        });
    }

    // ===== GESTIÓN DE COMPONENTES =====
    showComponent(componentId) {
        return new Promise((resolve) => {
            // Ocultar todos los componentes
            const components = document.querySelectorAll('.component');
            components.forEach(comp => {
                comp.classList.remove('active');
            });
            
            // Mostrar componente específico
            setTimeout(() => {
                const targetComponent = document.getElementById(componentId);
                if (targetComponent) {
                    targetComponent.classList.add('active');
                    this.focusFirstInput(componentId);
                    resolve();
                } else {
                    console.warn(`Component ${componentId} not found`);
                    resolve();
                }
            }, AppConfig.ANIMATION_DURATION / 2);
            
            // Gestionar botón de regreso
            this.updateBackButton(componentId);
            AppState.currentScreen = componentId;
        });
    }

    // Actualizar visibilidad del botón de regreso
    updateBackButton(componentId) {
        const backButton = document.getElementById('back-button');
        if (!backButton) return;

        if (componentId === 'main-screen') {
            backButton.classList.add('hidden');
        } else {
            backButton.classList.remove('hidden');
        }
    }

    // ===== FUNCIONES DE NAVEGACIÓN =====
    async showMain() {
        await this.changeBackground('main');
        await this.showComponent('main-screen');
    }

    async showCreateGame() {
        if (!AppState.user) {
            this.showNotification('Debes iniciar sesión para crear una partida', 'warning');
            await this.showLogin();
            return;
        }
        await this.changeBackground('interface');
        await this.showComponent('create-game-form');
    }

    async showLogin() {
        await this.changeBackground('interface');
        await this.showComponent('login-form');
    }

    async showRegister() {
        await this.changeBackground('interface');
        await this.showComponent('register-form');
    }

    async showTrackingMode() {
        await this.changeBackground('interface');
        await this.showComponent('tracking-mode');
    }

    async showAdminPanel() {
        if (!AppState.user || AppState.user.role !== 'admin') {
            this.showNotification('Acceso denegado', 'error');
            await this.showMain();
            return;
        }
        
        // Crear panel de administrador si no existe
        this.createAdminPanel();
        await this.changeBackground('interface');
        await this.showComponent('admin-panel');
    }

    // ===== MANEJO DE FORMULARIOS =====
    async handleNewGameSubmit(e) {
        e.preventDefault();
        
        const numPlayers = document.getElementById('num-players').value;
        const gameName = document.getElementById('game-name').value.trim();
        
        if (!numPlayers || !gameName) {
            this.showNotification('Por favor completa todos los campos', 'warning');
            return;
        }

        try {
            this.setLoading(true);
            
            // Crear nueva partida
            const gameData = await this.createNewGame({
                numPlayers: parseInt(numPlayers),
                gameName: gameName,
                mode: 'digital'
            });
            
            if (gameData.success) {
                AppState.gameData = gameData.data;
                this.setLoading(false);
                this.showNotification(`Partida "${gameName}" creada para ${numPlayers} jugadores`, 'success');
                await this.startDigitalGame();
            } else {
                throw new Error(gameData.message);
            }
            
        } catch (error) {
            this.setLoading(false);
            this.showNotification('Error al crear la partida: ' + error.message, 'error');
            console.error('Error creating game:', error);
        }
    }

    async handleLoginSubmit(e) {
        e.preventDefault();
        
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        
        if (!username || !password) {
            this.showNotification('Por favor completa todos los campos', 'warning');
            return;
        }

        try {
            this.setLoading(true);
            
            const loginData = await this.authenticateUser(username, password);
            
            if (loginData.success) {
                AppState.user = loginData.data.user;
                this.updateUIForLoggedUser();
                
                this.setLoading(false);
                this.showNotification(`Bienvenido, ${username}!`, 'success');
                
                // Redirigir según el tipo de usuario
                if (AppState.user.role === 'admin') {
                    await this.showAdminPanel();
                } else {
                    await this.showMain();
                }
            } else {
                throw new Error(loginData.message);
            }
            
        } catch (error) {
            this.setLoading(false);
            this.showNotification('Credenciales incorrectas', 'error');
            console.error('Login error:', error);
        }
    }

    async handleRegisterSubmit(e) {
        e.preventDefault();
        
        const username = document.getElementById('reg-username').value.trim();
        const email = document.getElementById('reg-email').value.trim();
        const birthdate = document.getElementById('reg-birthdate').value;
        const password = document.getElementById('reg-password').value;
        
        // Validaciones
        const validation = this.validateRegistrationData(username, email, birthdate, password);
        if (!validation.valid) {
            this.showNotification(validation.message, 'warning');
            return;
        }

        try {
            this.setLoading(true);
            
            const registerData = await this.registerUser({
                username,
                email,
                birthdate,
                password
            });
            
            if (registerData.success) {
                AppState.user = registerData.data.user;
                this.updateUIForLoggedUser();
                
                this.setLoading(false);
                this.showNotification(`¡Registro exitoso! Bienvenido, ${username}!`, 'success');
                await this.showMain();
            } else {
                throw new Error(registerData.message);
            }
            
        } catch (error) {
            this.setLoading(false);
            this.showNotification(error.message || 'Error al registrar usuario', 'error');
            console.error('Register error:', error);
        }
    }

    // ===== FUNCIONES DEL JUEGO =====
    async startDigitalGame() {
        try {
            await this.changeBackground('board');
            
            if (this.gameEngine && AppState.gameData) {
                this.gameEngine.initGame(AppState.gameData);
                
                if (this.tableroManager) {
                    this.tableroManager.startGame(AppState.gameData);
                }
                
                await this.showComponent('game-board');
            } else {
                throw new Error('Game engine not available');
            }
            
        } catch (error) {
            this.showNotification('Error al iniciar el juego: ' + error.message, 'error');
            await this.showMain();
        }
    }

    async startTracking() {
        try {
            this.setLoading(true);
            
            await this.changeBackground('board');
            
            // Configurar modo seguimiento
            const trackingData = {
                id: 'tracking-' + Date.now(),
                mode: 'seguimiento',
                numPlayers: 2 // Default, se puede cambiar
            };
            
            AppState.trackingData = trackingData;
            
            if (this.gameEngine) {
                this.gameEngine.initTrackingMode(trackingData.numPlayers);
            }
            
            if (this.tableroManager) {
                this.tableroManager.initTrackingMode(trackingData);
            }
            
            this.setLoading(false);
            this.showNotification('Modo seguimiento activado', 'success');
            await this.showComponent('tracking-board');
            
        } catch (error) {
            this.setLoading(false);
            this.showNotification('Error al iniciar el modo seguimiento: ' + error.message, 'error');
        }
    }

    // ===== API METHODS =====
    async checkSession() {
        const response = await fetch(`${AppConfig.API_BASE_URL}auth/session.php`, {
            method: 'GET',
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error('No active session');
        }
        
        return await response.json();
    }

    async authenticateUser(username, password) {
        const response = await fetch(`${AppConfig.API_BASE_URL}auth/login.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({ username, password })
        });
        
        return await response.json();
    }

    async registerUser(userData) {
        const response = await fetch(`${AppConfig.API_BASE_URL}auth/register.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(userData)
        });
        
        return await response.json();
    }

    async createNewGame(gameData) {
        const response = await fetch(`${AppConfig.API_BASE_URL}game/create.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(gameData)
        });
        
        return await response.json();
    }

    async logout() {
        try {
            await fetch(`${AppConfig.API_BASE_URL}auth/logout.php`, {
                method: 'POST',
                credentials: 'include'
            });
            
            AppState.user = null;
            AppState.gameData = null;
            AppState.trackingData = null;
            
            this.updateUIForLoggedUser();
            this.showNotification('Sesión cerrada correctamente', 'info');
            await this.showMain();
            
        } catch (error) {
            console.error('Logout error:', error);
        }
    }

    // ===== VALIDACIONES =====
    validateRegistrationData(username, email, birthdate, password) {
        if (!username || !email || !birthdate || !password) {
            return { valid: false, message: 'Por favor completa todos los campos' };
        }

        if (username.length < 3) {
            return { valid: false, message: 'El nombre de usuario debe tener al menos 3 caracteres' };
        }

        if (!this.validateEmail(email)) {
            return { valid: false, message: 'El formato del email no es válido' };
        }

        if (password.length < 6) {
            return { valid: false, message: 'La contraseña debe tener al menos 6 caracteres' };
        }

        // Validar edad mínima
        const birthDate = new Date(birthdate);
        const today = new Date();
        const age = today.getFullYear() - birthDate.getFullYear();
        
        if (age < 13) {
            return { valid: false, message: 'Debes ser mayor de 13 años para registrarte' };
        }

        return { valid: true };
    }

    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // ===== UI UPDATES =====
    updateUIForLoggedUser() {
        const mainScreen = document.getElementById('main-screen');
        if (!mainScreen) return;

        let menuButtons = mainScreen.querySelector('.menu-buttons');
        if (!menuButtons) return;

        if (AppState.user) {
            // Usuario logueado - mostrar opciones avanzadas
            menuButtons.innerHTML = `
                <button class="btn btn-verde" onclick="app.showCreateGame()">
                    <img src="img/iconos/icono_partidas-ganadas.png" alt="" class="btn-icon">
                    Nueva Partida
                </button>
                <button class="btn btn-naranja" onclick="app.showTrackingMode()">
                    <img src="img/iconos/icono_mapa.png" alt="" class="btn-icon">
                    Modo Seguimiento
                </button>
                ${AppState.user.role === 'admin' ? `
                <button class="btn" onclick="app.showAdminPanel()">
                    <img src="img/iconos/icono_informacion.png" alt="" class="btn-icon">
                    Panel Admin
                </button>` : ''}
                <button class="btn btn-secondary" onclick="app.logout()">
                    <img src="img/iconos/foto_usuario-1.png" alt="" class="btn-icon">
                    Cerrar Sesión (${AppState.user.username})
                </button>
            `;
        } else {
            // Usuario no logueado - mostrar opciones básicas
            menuButtons.innerHTML = `
                <button class="btn btn-verde" onclick="app.showCreateGame()">
                    <img src="img/iconos/icono_partidas-ganadas.png" alt="" class="btn-icon">
                    Nueva Partida
                </button>
                <button class="btn btn-naranja" onclick="app.showTrackingMode()">
                    <img src="img/iconos/icono_mapa.png" alt="" class="btn-icon">
                    Modo Seguimiento
                </button>
                <button class="btn" onclick="app.showLogin()">
                    <img src="img/iconos/foto_usuario-1.png" alt="" class="btn-icon">
                    Iniciar Sesión
                </button>
                <button class="btn" onclick="app.showRegister()">
                    <img src="img/iconos/icono_mail.png" alt="" class="btn-icon">
                    Registrarse
                </button>
            `;
        }
    }

    // ===== CREAR COMPONENTES DINÁMICOS =====
    createAdminPanel() {
        const existingPanel = document.getElementById('admin-panel');
        if (existingPanel) return;

        const adminPanelHTML = `
            <div class="component form-container" id="admin-panel">
                <div class="form-header">
                    <img src="img/iconos/icono_informacion.png" alt="" class="form-icon">
                    <h2 class="form-title">Panel de Administración</h2>
                </div>
                
                <div class="admin-sections">
                    <div class="admin-section">
                        <h3>Gestión de Usuarios</h3>
                        <div class="admin-stats" id="user-stats">
                            <div class="stat-item">
                                <span class="stat-label">Usuarios Totales:</span>
                                <span class="stat-value" id="total-users">--</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Activos Hoy:</span>
                                <span class="stat-value" id="active-today">--</span>
                            </div>
                        </div>
                        <button class="btn btn-verde" onclick="app.loadUserManagement()">
                            Ver Usuarios
                        </button>
                    </div>
                    
                    <div class="admin-section">
                        <h3>Estadísticas de Partidas</h3>
                        <div class="admin-stats" id="game-stats">
                            <div class="stat-item">
                                <span class="stat-label">Partidas Totales:</span>
                                <span class="stat-value" id="total-games">--</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">En Curso:</span>
                                <span class="stat-value" id="active-games">--</span>
                            </div>
                        </div>
                        <button class="btn btn-naranja" onclick="app.loadGameStats()">
                            Ver Estadísticas
                        </button>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="app.showMain()">
                        Volver al Menú
                    </button>
                </div>
            </div>
        `;

        const contentContainer = document.querySelector('.content-container');
        contentContainer.insertAdjacentHTML('beforeend', adminPanelHTML);

        // Agregar estilos específicos del panel admin
        if (!document.querySelector('#admin-panel-styles')) {
            const style = document.createElement('style');
            style.id = 'admin-panel-styles';
            style.textContent = `
                .admin-sections {
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                    margin: 1.5rem 0;
                }
                
                .admin-section {
                    background: rgba(255, 255, 255, 0.05);
                    padding: 1rem;
                    border-radius: 10px;
                    border: 1px solid rgba(255, 241, 199, 0.1);
                }
                
                .admin-section h3 {
                    font-family: 'Oswald', sans-serif;
                    color: var(--color-primary);
                    margin-bottom: 1rem;
                    font-size: 1.2rem;
                }
                
                .admin-stats {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                    margin-bottom: 1rem;
                }
                
                .stat-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    font-size: 0.9rem;
                }
                
                .stat-label {
                    color: var(--color-text-light);
                }
                
                .stat-value {
                    color: var(--color-primary);
                    font-weight: 600;
                }
                
                @media (max-width: 768px) {
                    .admin-sections {
                        gap: 1rem;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // Cargar datos iniciales del panel
        this.loadAdminData();
    }

    async loadAdminData() {
        try {
            const response = await fetch(`${AppConfig.API_BASE_URL}admin/stats.php`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateAdminStats(data.data);
                }
            }
        } catch (error) {
            console.error('Error loading admin data:', error);
        }
    }

    updateAdminStats(stats) {
        const elements = {
            'total-users': stats.totalUsers || 0,
            'active-today': stats.activeToday || 0,
            'total-games': stats.totalGames || 0,
            'active-games': stats.activeGames || 0
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    // ===== UTILIDADES =====
    setLoading(isLoading) {
        AppState.isLoading = isLoading;
        const activeComponent = document.querySelector('.component.active');
        
        if (activeComponent) {
            if (isLoading) {
                activeComponent.classList.add('loading');
            } else {
                activeComponent.classList.remove('loading');
            }
        }
    }

    focusFirstInput(componentId) {
        setTimeout(() => {
            const component = document.getElementById(componentId);
            const firstInput = component?.querySelector('input, select, textarea');
            if (firstInput) {
                firstInput.focus();
            }
        }, AppConfig.ANIMATION_DURATION);
    }

    handleKeyNavigation(e) {
        if (e.key === 'Escape' && AppState.currentScreen !== 'main-screen') {
            this.showMain();
        }
    }

    handleOrientationChange() {
        // Reajustar elementos si es necesario
        if (AppState.currentScreen === 'game-board' || AppState.currentScreen === 'tracking-board') {
            if (this.tableroManager) {
                this.tableroManager.handleResize();
            }
        }
    }

    showNotification(message, type = 'info') {
        // Crear elemento de notificación
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Agregar estilos si no existen
        if (!document.querySelector('#notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: rgba(0, 0, 0, 0.9);
                    color: var(--color-text-light);
                    padding: 15px 20px;
                    border-radius: 10px;
                    border-left: 4px solid var(--color-primary);
                    z-index: 1000;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    max-width: 300px;
                    backdrop-filter: blur(10px);
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                    font-size: 0.9rem;
                }
                
                .notification.show {
                    transform: translateX(0);
                }
                
                .notification-success { border-left-color: var(--color-success); }
                .notification-error { border-left-color: var(--color-danger); }
                .notification-warning { border-left-color: var(--color-warning); }
                .notification-info { border-left-color: var(--color-info); }
                
                .notification-message {
                    flex: 1;
                }
                
                .notification-close {
                    background: none;
                    border: none;
                    color: var(--color-text-light);
                    cursor: pointer;
                    font-size: 1.2rem;
                    padding: 0;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .notification-close:hover {
                    opacity: 0.7;
                }
                
                @media (max-width: 768px) {
                    .notification {
                        left: 20px;
                        right: 20px;
                        max-width: none;
                    }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Agregar al DOM
        document.body.appendChild(notification);
        
        // Mostrar con animación
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }, 5000);
    }
}

// ===== FUNCIONES GLOBALES PARA HTML =====
let app;

function showMain() {
    app?.showMain();
}

function showCreateGame() {
    app?.showCreateGame();
}

function showLogin() {
    app?.showLogin();
}

function showRegister() {
    app?.showRegister();
}

function showTrackingMode() {
    app?.showTrackingMode();
}

function startTracking() {
    app?.startTracking();
}

function logout() {
    app?.logout();
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    app = new DraftosaurusApp();
    
    // Manejo de errores globales
    window.addEventListener('error', function(e) {
        console.error('Global error:', e.error);
        app?.showNotification('Ha ocurrido un error inesperado', 'error');
    });
    
    // Manejo de errores de red
    window.addEventListener('unhandledrejection', function(e) {
        console.error('Unhandled promise rejection:', e.reason);
        if (e.reason?.message?.includes('fetch')) {
            app?.showNotification('Error de conexión', 'error');
        }
    });
});

// Exportar para uso en otros módulos si es necesario
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DraftosaurusApp;
}

class DraftosaurusAppComplete extends DraftosaurusApp {
    constructor() {
        super();
        this.advancedFeatures = null;
        this.analytics = null;
        this.liveStats = null;
    }

    async startDigitalGame() {
        try {
            await this.changeBackground('board');
            
            if (this.gameEngine && AppState.gameData) {
                this.gameEngine.initGame(AppState.gameData);
                
                if (this.tableroManager) {
                    this.tableroManager.startGame(AppState.gameData);
                    
                    // Inicializar funcionalidades avanzadas
                    this.initAdvancedFeatures();
                }
                
                await this.showComponent('game-board');
                
                // Intentar cargar estado guardado
                await this.tryLoadSavedGame();
            } else {
                throw new Error('Game engine not available');
            }
            
        } catch (error) {
            this.showNotification('Error al iniciar el juego: ' + error.message, 'error');
            await this.showMain();
        }
    }

    async startTracking() {
        try {
            this.setLoading(true);
            
            await this.changeBackground('board');
            
            const trackingData = {
                id: 'tracking-' + Date.now(),
                mode: 'seguimiento',
                numPlayers: 2
            };
            
            AppState.trackingData = trackingData;
            
            if (this.gameEngine) {
                this.gameEngine.initTrackingMode(trackingData.numPlayers);
            }
            
            if (this.tableroManager) {
                this.tableroManager.initTrackingMode(trackingData);
            }
            
            this.setLoading(false);
            this.showNotification('Modo seguimiento activado', 'success');
            await this.showComponent('tracking-board');
            
        } catch (error) {
            this.setLoading(false);
            this.showNotification('Error al iniciar el modo seguimiento: ' + error.message, 'error');
        }
    }

    initAdvancedFeatures() {
        if (this.gameEngine && this.tableroManager && !this.gameEngine.isTrackingMode) {
            const features = initializeAdvancedFeatures(this.gameEngine, this.tableroManager);
            
            this.advancedFeatures = features.advancedFeatures;
            this.analytics = features.analytics;
            this.liveStats = features.liveStats;
            
            // Agregar botones de funcionalidades avanzadas
            this.addAdvancedButtons();
        }
    }

    addAdvancedButtons() {
        const gameControls = document.querySelector('.game-controls');
        if (!gameControls) return;

        const advancedButtonsHTML = `
            <button class="btn btn-secondary" onclick="app.exportGame('json')" title="Ctrl+E">
                📁 Exportar
            </button>
            <button class="btn btn-secondary" onclick="app.toggleStats()" title="F2">
                📊 Stats
            </button>
            <button class="btn btn-secondary" onclick="app.toggleFullscreen()" title="F">
                🔳 Pantalla Completa
            </button>
        `;

        gameControls.insertAdjacentHTML('beforeend', advancedButtonsHTML);
    }

    async tryLoadSavedGame() {
        if (!AppState.gameData?.id || this.gameEngine.isTrackingMode) return;

        try {
            const success = await this.advancedFeatures?.loadGameState(AppState.gameData.id);
            if (success) {
                this.showNotification('Partida recuperada automáticamente', 'success');
            }
        } catch (error) {
            console.warn('No se pudo cargar estado guardado:', error);
        }
    }

    // Funciones para los botones avanzados
    exportGame(format = 'json') {
        if (this.advancedFeatures) {
            this.advancedFeatures.exportGameData(format);
        } else {
            this.showNotification('Función no disponible en modo seguimiento', 'warning');
        }
    }

    toggleStats() {
        if (this.liveStats) {
            if (this.liveStats.isVisible) {
                this.liveStats.hide();
            } else {
                this.liveStats.show();
            }
        }
    }

    toggleFullscreen() {
        if (this.advancedFeatures) {
            this.advancedFeatures.toggleFullscreen();
        }
    }

    // Sobrescribir createAdminPanel para incluir más funcionalidades
    createAdminPanel() {
        super.createAdminPanel();
        
        // Agregar sección de analytics
        const adminPanel = document.getElementById('admin-panel');
        if (adminPanel) {
            const analyticsSection = document.createElement('div');
            analyticsSection.className = 'admin-section';
            analyticsSection.innerHTML = `
                <h3>Analytics del Sistema</h3>
                <div class="admin-stats" id="analytics-stats">
                    <div class="stat-item">
                        <span class="stat-label">Sesiones hoy:</span>
                        <span class="stat-value" id="sessions-today">--</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Tiempo promedio sesión:</span>
                        <span class="stat-value" id="avg-session-time">--</span>
                    </div>
                </div>
                <button class="btn btn-verde" onclick="app.loadAnalyticsData()">
                    Ver Analytics
                </button>
            `;
            
            const adminSections = adminPanel.querySelector('.admin-sections');
            if (adminSections) {
                adminSections.appendChild(analyticsSection);
            }
        }
    }

    async loadAnalyticsData() {
        try {
            const response = await fetch('api/admin/analytics.php', {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateAnalyticsStats(data.data);
                }
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
        }
    }

    updateAnalyticsStats(data) {
        const elements = {
            'sessions-today': data.sessionsToday || 0,
            'avg-session-time': (data.avgSessionTime || 0) + ' min'
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    // Cleanup mejorado
    cleanup() {
        if (this.advancedFeatures) {
            this.advancedFeatures.cleanup();
        }
        
        if (this.analytics) {
            this.analytics.sendAnalyticsToServer();
        }
        
        if (this.liveStats) {
            this.liveStats.destroy();
        }
        
        // Cleanup del tablero manager
        if (this.tableroManager) {
            this.tableroManager.cleanup();
        }
    }
}

// Sobrescribir la instancia global
document.addEventListener('DOMContentLoaded', function() {
    // Reemplazar la instancia anterior
    if (window.app) {
        window.app.cleanup();
    }
    
    window.app = new DraftosaurusAppComplete();
    
    // Agregar atajos de teclado globales
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        
        // Ctrl+E para exportar
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            window.app.exportGame();
        }
        
        // F2 para toggle stats
        if (e.key === 'F2') {
            e.preventDefault();
            window.app.toggleStats();
        }
    });
    
    // Cleanup al cerrar
    window.addEventListener('beforeunload', () => {
        window.app.cleanup();
    });
});

// Funciones globales adicionales
function exportGame(format = 'json') {
    window.app?.exportGame(format);
}

function toggleStats() {
    window.app?.toggleStats();
}

function toggleFullscreen() {
    window.app?.toggleFullscreen();
}

function loadAnalyticsData() {
    window.app?.loadAnalyticsData();
}

// Utilidad para verificar completitud del sistema
function verifySystemIntegrity() {
    const checks = {
        gameEngine: typeof window.GameEngine !== 'undefined',
        tableroManager: typeof window.TableroManager !== 'undefined',
        advancedFeatures: typeof window.AdvancedGameFeatures !== 'undefined',
        analytics: typeof window.GameAnalytics !== 'undefined',
        validators: typeof window.Validators !== 'undefined',
        domUtils: typeof window.DOMUtils !== 'undefined',
        gameUtils: typeof window.GameUtils !== 'undefined',
        apiUtils: typeof window.APIUtils !== 'undefined'
    };
    
    const missing = Object.entries(checks)
        .filter(([key, exists]) => !exists)
        .map(([key]) => key);
    
    if (missing.length > 0) {
        console.warn('Sistema incompleto. Faltan:', missing);
        return false;
    }
    
    console.log('✅ Sistema completamente funcional');
    return true;
}

// Auto-verificación al cargar
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        verifySystemIntegrity();
    }, 1000);
});

// Debug helpers
window.debugDraftosaurus = {
    verifySystem: verifySystemIntegrity,
    getAppState: () => window.AppState,
    getGameState: () => window.app?.gameEngine?.getGameState(),
    exportDebugInfo: () => {
        const debugInfo = {
            appState: window.AppState,
            gameState: window.app?.gameEngine?.getGameState(),
            browserInfo: {
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                localStorage: !!window.localStorage,
                sessionStorage: !!window.sessionStorage
            },
            systemChecks: verifySystemIntegrity()
        };
        
        console.log('Debug Info:', debugInfo);
        
        // Descargar como JSON
        const blob = new Blob([JSON.stringify(debugInfo, null, 2)], { type: 'application/json' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `draftosaurus_debug_${Date.now()}.json`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        return debugInfo;
    }
};