<?php
/**
 * Script de instalación automática para Draftosaurus I.G.P.D.
 * Configura la base de datos y verifica requisitos del sistema
 */

// Configuración de instalación
$config = [
    'db_host' => 'localhost',
    'db_name' => 'draftosaurus_db',
    'db_user' => 'root',
    'db_pass' => '',
    'admin_user' => 'admin',
    'admin_pass' => 'password',
    'admin_email' => 'admin@draftosaurus.local'
];

// Verificar si es una solicitud AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjax) {
    header('Content-Type: application/json');
    
    try {
        $step = $_POST['step'] ?? '';
        
        switch ($step) {
            case 'check_requirements':
                echo json_encode(checkRequirements());
                break;
                
            case 'test_database':
                $dbConfig = $_POST['db_config'] ?? [];
                echo json_encode(testDatabaseConnection($dbConfig));
                break;
                
            case 'install_database':
                $dbConfig = $_POST['db_config'] ?? [];
                echo json_encode(installDatabase($dbConfig));
                break;
                
            case 'create_admin':
                $adminConfig = $_POST['admin_config'] ?? [];
                $dbConfig = $_POST['db_config'] ?? [];
                echo json_encode(createAdminUser($adminConfig, $dbConfig));
                break;
                
            default:
                throw new Exception('Paso de instalación no válido');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Funciones de instalación
function checkRequirements() {
    $requirements = [
        'php_version' => [
            'name' => 'PHP 8.0+',
            'check' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'current' => PHP_VERSION
        ],
        'pdo' => [
            'name' => 'PDO Extension',
            'check' => extension_loaded('pdo'),
            'current' => extension_loaded('pdo') ? 'Instalado' : 'No instalado'
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL Driver',
            'check' => extension_loaded('pdo_mysql'),
            'current' => extension_loaded('pdo_mysql') ? 'Instalado' : 'No instalado'
        ],
        'json' => [
            'name' => 'JSON Extension',
            'check' => extension_loaded('json'),
            'current' => extension_loaded('json') ? 'Instalado' : 'No instalado'
        ],
        'session' => [
            'name' => 'Session Support',
            'check' => function_exists('session_start'),
            'current' => function_exists('session_start') ? 'Disponible' : 'No disponible'
        ],
        'uploads_dir' => [
            'name' => 'Directorio uploads/ escribible',
            'check' => createDirectoryIfNotExists('../../uploads/'),
            'current' => is_writable('../../uploads/') ? 'Escribible' : 'No escribible'
        ],
        'logs_dir' => [
            'name' => 'Directorio logs/ escribible',
            'check' => createDirectoryIfNotExists('../../logs/'),
            'current' => is_writable('../../logs/') ? 'Escribible' : 'No escribible'
        ]
    ];
    
    $allPassed = true;
    foreach ($requirements as $req) {
        if (!$req['check']) {
            $allPassed = false;
            break;
        }
    }
    
    return [
        'success' => true,
        'requirements' => $requirements,
        'all_passed' => $allPassed
    ];
}

function createDirectoryIfNotExists($path) {
    if (!is_dir($path)) {
        return mkdir($path, 0755, true);
    }
    return is_writable($path);
}

function testDatabaseConnection($config) {
    try {
        $dsn = "mysql:host={$config['host']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Verificar si la base de datos existe
        $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
        $stmt->execute([$config['name']]);
        $dbExists = $stmt->fetch() !== false;
        
        return [
            'success' => true,
            'message' => 'Conexión exitosa',
            'database_exists' => $dbExists
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Error de conexión: ' . $e->getMessage()
        ];
    }
}

function installDatabase($config) {
    try {
        // Conectar sin especificar base de datos
        $dsn = "mysql:host={$config['host']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Leer y ejecutar el script SQL
        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception('Archivo database.sql no encontrado');
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Dividir en statements individuales
        $statements = explode(';', $sql);
        
        $executed = 0;
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^\s*(--|\#|\/\*)/', $statement)) {
                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Ignorar errores de "tabla ya existe" o similares
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        throw $e;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'message' => "Base de datos instalada correctamente. {$executed} statements ejecutados.",
            'statements_executed' => $executed
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al instalar base de datos: ' . $e->getMessage()
        ];
    }
}

function createAdminUser($adminConfig, $dbConfig) {
    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Verificar si el usuario admin ya existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ?");
        $stmt->execute([$adminConfig['username'], $adminConfig['email']]);
        
        if ($stmt->fetch()) {
            // Actualizar usuario existente
            $stmt = $pdo->prepare("
                UPDATE usuarios 
                SET email = ?, password_hash = ?, role = 'admin', activo = 1 
                WHERE username = ?
            ");
            $stmt->execute([
                $adminConfig['email'],
                password_hash($adminConfig['password'], PASSWORD_DEFAULT),
                $adminConfig['username']
            ]);
            $message = 'Usuario administrador actualizado';
        } else {
            // Crear nuevo usuario
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (username, email, password_hash, fecha_nacimiento, role, activo) 
                VALUES (?, ?, ?, ?, 'admin', 1)
            ");
            $stmt->execute([
                $adminConfig['username'],
                $adminConfig['email'],
                password_hash($adminConfig['password'], PASSWORD_DEFAULT),
                '1990-01-01'
            ]);
            $message = 'Usuario administrador creado';
        }
        
        // Crear archivo de configuración
        createConfigFile($dbConfig);
        
        return [
            'success' => true,
            'message' => $message
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error al crear usuario administrador: ' . $e->getMessage()
        ];
    }
}

function createConfigFile($dbConfig) {
    $configContent = "<?php
/**
 * Configuración generada automáticamente por el instalador
 * Fecha: " . date('Y-m-d H:i:s') . "
 */

// Configuración de la base de datos
define('DB_HOST', '{$dbConfig['host']}');
define('DB_NAME', '{$dbConfig['name']}');
define('DB_USER', '{$dbConfig['user']}');
define('DB_PASS', '{$dbConfig['password']}');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'Draftosaurus I.G.P.D.');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production');

// Configuración de sesiones
define('SESSION_LIFETIME', 3600 * 24);
define('SESSION_NAME', 'DRAFTOSAURUS_SESSION');

// Configuración de seguridad
define('BCRYPT_COST', 12);
define('JWT_SECRET', '" . bin2hex(random_bytes(32)) . "');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// Configuración de archivos
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Configuración de juego
define('MIN_PLAYERS', 2);
define('MAX_PLAYERS', 5);
define('MAX_GAME_NAME_LENGTH', 50);
define('MAX_USERNAME_LENGTH', 20);
define('MIN_PASSWORD_LENGTH', 6);

// Rutas de la aplicación
define('API_BASE_PATH', '/api/');
define('UPLOAD_PATH', '../uploads/');
define('LOG_PATH', '../logs/');

// Configuración de logs
define('LOG_ERRORS', true);
define('LOG_QUERIES', false);

// Zona horaria
date_default_timezone_set('America/Montevideo');

// Configuración de errores para producción
error_reporting(0);
ini_set('display_errors', 0);

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Funciones utilitarias
function logError(\$message, \$context = []) {
    if (!LOG_ERRORS) return;
    
    \$logFile = LOG_PATH . 'error_' . date('Y-m-d') . '.log';
    \$timestamp = date('Y-m-d H:i:s');
    \$contextStr = !empty(\$context) ? ' | Context: ' . json_encode(\$context) : '';
    
    \$logMessage = \"[{\$timestamp}] ERROR: {\$message}{\$contextStr}\" . PHP_EOL;
    
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    file_put_contents(\$logFile, \$logMessage, FILE_APPEND | LOCK_EX);
}

function jsonResponse(\$data = null, \$message = '', \$success = true, \$httpCode = 200) {
    http_response_code(\$httpCode);
    header('Content-Type: application/json; charset=utf-8');
    
    \$response = [
        'success' => \$success,
        'message' => \$message,
        'timestamp' => date('c')
    ];
    
    if (\$data !== null) {
        \$response['data'] = \$data;
    }
    
    echo json_encode(\$response, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeInput(\$data) {
    if (is_array(\$data)) {
        return array_map('sanitizeInput', \$data);
    }
    return htmlspecialchars(strip_tags(trim(\$data)), ENT_QUOTES, 'UTF-8');
}

function isValidEmail(\$email) {
    return filter_var(\$email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateSecureToken(\$length = 32) {
    return bin2hex(random_bytes(\$length));
}
?>";

    $configPath = __DIR__ . '/../config/config.php';
    
    // Crear directorio config si no existe
    $configDir = dirname($configPath);
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }
    
    return file_put_contents($configPath, $configContent) !== false;
}

// Si no es AJAX, mostrar interfaz de instalación
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Draftosaurus I.G.P.D.</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2A3502 0%, #3A4D03 100%);
            color: #FFF1C7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .installer-container {
            background: rgba(0, 0, 0, 0.8);
            padding: 2rem;
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 241, 199, 0.2);
        }

        .installer-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .installer-title {
            font-size: 2rem;
            color: #F8C94E;
            margin-bottom: 0.5rem;
        }

        .installer-subtitle {
            color: #FFF1C7;
            opacity: 0.8;
        }

        .step {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .step.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #F8C94E;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255, 241, 199, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: #FFF1C7;
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: #F8C94E;
            background: rgba(255, 255, 255, 0.15);
        }

        .btn {
            background: linear-gradient(to bottom, rgba(0, 0, 0, 1) 0%, rgb(0 0 0 / 36%) 100%);
            color: #FFF1C7;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        .btn:hover {
            background: linear-gradient(to right, #3A4D03, #5A7B05);
            transform: translateY(-1px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid #FFF1C7;
        }

        .btn-secondary:hover {
            background: rgba(255, 241, 199, 0.1);
        }

        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 241, 199, 0.1);
        }

        .requirement-status {
            font-weight: bold;
        }

        .status-pass {
            color: #4CAF50;
        }

        .status-fail {
            color: #f44336;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 241, 199, 0.2);
            border-radius: 3px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, #3A4D03, #5A7B05);
            width: 0%;
            transition: width 0.3s ease;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.2);
            border-left: 4px solid #4CAF50;
        }

        .message.error {
            background: rgba(244, 67, 54, 0.2);
            border-left: 4px solid #f44336;
        }

        .message.info {
            background: rgba(33, 150, 243, 0.2);
            border-left: 4px solid #2196F3;
        }

        .step-buttons {
            margin-top: 2rem;
            text-align: right;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 241, 199, 0.3);
            border-radius: 50%;
            border-top-color: #F8C94E;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1 class="installer-title">Draftosaurus I.G.P.D.</h1>
            <p class="installer-subtitle">Asistente de Instalación</p>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" id="progress"></div>
        </div>

        <!-- Paso 1: Verificar Requisitos -->
        <div class="step active" id="step-1">
            <h2 class="step-title">1. Verificar Requisitos del Sistema</h2>
            <div id="requirements-list">
                <p>Verificando requisitos del sistema...</p>
            </div>
            <div class="step-buttons">
                <button class="btn" onclick="checkRequirements()">Verificar Requisitos</button>
            </div>
        </div>

        <!-- Paso 2: Configuración de Base de Datos -->
        <div class="step" id="step-2">
            <h2 class="step-title">2. Configuración de Base de Datos</h2>
            <form id="db-config-form">
                <div class="form-group">
                    <label class="form-label">Servidor de Base de Datos</label>
                    <input type="text" class="form-input" name="host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre de la Base de Datos</label>
                    <input type="text" class="form-input" name="name" value="draftosaurus_db" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Usuario de Base de Datos</label>
                    <input type="text" class="form-input" name="user" value="root" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña de Base de Datos</label>
                    <input type="password" class="form-input" name="password" value="">
                </div>
            </form>
            <div id="db-test-result"></div>
            <div class="step-buttons">
                <button class="btn btn-secondary" onclick="prevStep()">Anterior</button>
                <button class="btn" onclick="testDatabase()">Probar Conexión</button>
            </div>
        </div>

        <!-- Paso 3: Instalación de Base de Datos -->
        <div class="step" id="step-3">
            <h2 class="step-title">3. Instalación de Base de Datos</h2>
            <p>Se creará la estructura de la base de datos con todas las tablas necesarias.</p>
            <div id="db-install-result"></div>
            <div class="step-buttons">
                <button class="btn btn-secondary" onclick="prevStep()">Anterior</button>
                <button class="btn" onclick="installDatabase()">Instalar Base de Datos</button>
            </div>
        </div>

        <!-- Paso 4: Crear Usuario Administrador -->
        <div class="step" id="step-4">
            <h2 class="step-title">4. Crear Usuario Administrador</h2>
            <form id="admin-config-form">
                <div class="form-group">
                    <label class="form-label">Nombre de Usuario</label>
                    <input type="text" class="form-input" name="username" value="admin" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" name="email" value="admin@draftosaurus.local" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" class="form-input" name="password" value="password" required>
                </div>
            </form>
            <div id="admin-create-result"></div>
            <div class="step-buttons">
                <button class="btn btn-secondary" onclick="prevStep()">Anterior</button>
                <button class="btn" onclick="createAdmin()">Crear Administrador</button>
            </div>
        </div>

        <!-- Paso 5: Finalización -->
        <div class="step" id="step-5">
            <h2 class="step-title">5. Instalación Completada</h2>
            <div class="message success">
                <h3>¡Instalación exitosa!</h3>
                <p>Draftosaurus I.G.P.D. ha sido instalado correctamente.</p>
            </div>
            <div class="message info">
                <h4>Próximos pasos:</h4>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Elimina la carpeta <code>api/install/</code> por seguridad</li>
                    <li>Accede a la aplicación en la página principal</li>
                    <li>Inicia sesión con las credenciales de administrador</li>
                </ul>
            </div>
            <div class="step-buttons">
                <button class="btn" onclick="window.location.href='../../index.html'">Ir a la Aplicación</button>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let dbConfig = {};
        
        function updateProgress() {
            const progress = (currentStep - 1) * 25;
            document.getElementById('progress').style.width = progress + '%';
        }
        
        function showStep(step) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById(`step-${step}`).classList.add('active');
            currentStep = step;
            updateProgress();
        }
        
        function nextStep() {
            if (currentStep < 5) {
                showStep(currentStep + 1);
            }
        }
        
        function prevStep() {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }
        
        function showMessage(container, message, type = 'info') {
            container.innerHTML = `<div class="message ${type}">${message}</div>`;
        }
        
        function showLoading(button) {
            button.innerHTML = '<span class="loading"></span>' + button.textContent;
            button.disabled = true;
        }
        
        function hideLoading(button, text) {
            button.innerHTML = text;
            button.disabled = false;
        }
        
        async function apiCall(data) {
            const response = await fetch('install.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams(data)
            });
            
            return await response.json();
        }
        
        async function checkRequirements() {
            const button = event.target;
            showLoading(button);
            
            try {
                const result = await apiCall({ step: 'check_requirements' });
                const container = document.getElementById('requirements-list');
                
                if (result.success) {
                    let html = '';
                    for (const [key, req] of Object.entries(result.requirements)) {
                        const status = req.check ? 'pass' : 'fail';
                        const statusText = req.check ? '✓ Cumple' : '✗ No cumple';
                        html += `
                            <div class="requirement-item">
                                <span>${req.name}: ${req.current}</span>
                                <span class="requirement-status status-${status}">${statusText}</span>
                            </div>
                        `;
                    }
                    container.innerHTML = html;
                    
                    if (result.all_passed) {
                        showMessage(container, 'Todos los requisitos se cumplen. Puedes continuar con la instalación.', 'success');
                        setTimeout(() => nextStep(), 1500);
                    } else {
                        showMessage(container, 'Algunos requisitos no se cumplen. Por favor, corrígelos antes de continuar.', 'error');
                    }
                } else {
                    showMessage(container, 'Error al verificar requisitos: ' + result.message, 'error');
                }
            } catch (error) {
                showMessage(document.getElementById('requirements-list'), 'Error de conexión: ' + error.message, 'error');
            }
            
            hideLoading(button, 'Verificar Requisitos');
        }
        
        async function testDatabase() {
            const button = event.target;
            showLoading(button);
            
            const form = document.getElementById('db-config-form');
            const formData = new FormData(form);
            dbConfig = Object.fromEntries(formData);
            
            try {
                const result = await apiCall({
                    step: 'test_database',
                    db_config: dbConfig
                });
                
                const container = document.getElementById('db-test-result');
                
                if (result.success) {
                    let message = result.message;
                    if (result.database_exists) {
                        message += ' (La base de datos ya existe)';
                    }
                    showMessage(container, message, 'success');
                    
                    // Cambiar botón para continuar
                    button.textContent = 'Continuar';
                    button.onclick = nextStep;
                } else {
                    showMessage(container, result.message, 'error');
                }
            } catch (error) {
                showMessage(document.getElementById('db-test-result'), 'Error: ' + error.message, 'error');
            }
            
            hideLoading(button, 'Probar Conexión');
        }
        
        async function installDatabase() {
            const button = event.target;
            showLoading(button);
            
            try {
                const result = await apiCall({
                    step: 'install_database',
                    db_config: dbConfig
                });
                
                const container = document.getElementById('db-install-result');
                
                if (result.success) {
                    showMessage(container, result.message, 'success');
                    setTimeout(() => nextStep(), 1500);
                } else {
                    showMessage(container, result.message, 'error');
                }
            } catch (error) {
                showMessage(document.getElementById('db-install-result'), 'Error: ' + error.message, 'error');
            }
            
            hideLoading(button, 'Instalar Base de Datos');
        }
        
        async function createAdmin() {
            const button = event.target;
            showLoading(button);
            
            const form = document.getElementById('admin-config-form');
            const formData = new FormData(form);
            const adminConfig = Object.fromEntries(formData);
            
            try {
                const result = await apiCall({
                    step: 'create_admin',
                    admin_config: adminConfig,
                    db_config: dbConfig
                });
                
                const container = document.getElementById('admin-create-result');
                
                if (result.success) {
                    showMessage(container, result.message, 'success');
                    setTimeout(() => nextStep(), 1500);
                } else {
                    showMessage(container, result.message, 'error');
                }
            } catch (error) {
                showMessage(document.getElementById('admin-create-result'), 'Error: ' + error.message, 'error');
            }
            
            hideLoading(button, 'Crear Administrador');
        }
        
        // Verificar requisitos al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
        });
    </script>
</body>
</html>