<?php
/**
 * Configuración completa unificada de Draftosaurus I.G.P.D.
 * Sistema Informático de Gestión de Partidas para Draftosaurus
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'draftosaurus_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_NAME', 'Draftosaurus I.G.P.D.');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // development, production

// Configuración de sesiones
define('SESSION_LIFETIME', 3600 * 24); // 24 horas
define('SESSION_NAME', 'DRAFTOSAURUS_SESSION');

// Configuración de seguridad
define('BCRYPT_COST', 12);
define('JWT_SECRET', 'draftosaurus-secret-key-2025-' . bin2hex(random_bytes(16)));
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configuración de archivos
define('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB
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
define('LOG_QUERIES', APP_ENV === 'development');

// Zona horaria
date_default_timezone_set('America/Montevideo');

// Configuración de errores según el entorno
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS para desarrollo
if (APP_ENV === 'development') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    
    // Manejar preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Autoload de clases
spl_autoload_register(function ($class_name) {
    $directories = [
        __DIR__ . '/classes/',
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
        __DIR__ . '/utils/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Crear directorios necesarios si no existen
$directories = [LOG_PATH, UPLOAD_PATH, UPLOAD_PATH . 'profiles/', UPLOAD_PATH . 'temp/'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ===== FUNCIONES UTILITARIAS =====

/**
 * Función de logging mejorada
 */
function logError($message, $context = []) {
    if (!LOG_ERRORS) return;
    
    $logFile = LOG_PATH . 'error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $userInfo = '';
    
    // Agregar información del usuario si está disponible
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
        $userInfo = ' | User: ' . $_SESSION['user_id'];
    }
    
    $logMessage = "[{$timestamp}] ERROR: {$message}{$userInfo}{$contextStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Log de acciones del juego
 */
function logGameAction($action, $gameId = null, $userId = null, $data = []) {
    $logFile = LOG_PATH . 'game_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = !empty($data) ? ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    
    $logMessage = "[{$timestamp}] GAME: {$action} | GameID: {$gameId} | UserID: {$userId}{$dataStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Función para respuestas JSON consistentes
 */
function jsonResponse($data = null, $message = '', $success = true, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Función para validar y sanitizar datos de entrada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validación de email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generar token seguro
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Validar fortaleza de contraseña
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'La contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una letra minúscula';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos una letra mayúscula';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'La contraseña debe contener al menos un número';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Formatear fecha para mostrar
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (is_string($date)) {
        $date = new DateTime($date);
    }
    return $date->format($format);
}

/**
 * Calcular edad desde fecha de nacimiento
 */
function calculateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    return $today->diff($birth)->y;
}

/**
 * Generar foto de perfil aleatoria
 */
function getRandomProfilePicture() {
    $pictures = [
        'foto_usuario-1.png',
        'foto_usuario-2.png',
        'foto_usuario-3.png',
        'foto_usuario-4.png',
        'foto_usuario-5.png'
    ];
    
    return 'img/iconos/' . $pictures[array_rand($pictures)];
}

/**
 * Verificar permisos de usuario
 */
function checkUserPermission($requiredRole = 'player') {
    session_name(SESSION_NAME);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(null, 'Sesión requerida', false, 401);
    }
    
    if ($requiredRole === 'admin' && $_SESSION['role'] !== 'admin') {
        jsonResponse(null, 'Permisos insuficientes', false, 403);
    }
    
    return true;
}

/**
 * Validar estado de juego
 */
function validateGameState($gameData, $expectedStates = ['en_curso']) {
    if (!in_array($gameData['estado'], $expectedStates)) {
        return [
            'valid' => false,
            'message' => 'Estado de juego no válido: ' . $gameData['estado']
        ];
    }
    
    return ['valid' => true];
}

/**
 * Calcular puntuación según reglas de Draftosaurus
 */
function calculateDraftosaurusScore($recintoId, $dinosaurs) {
    $score = 0;
    $count = count($dinosaurs);
    
    if ($count === 0) return 0;
    
    switch ($recintoId) {
        case 1: // Bosque de la Semejanza
        case 2: // Prado de la Diferencia
            $score = getScoreByCount($count);
            break;
            
        case 3: // Pradera del Amor
            $score = calculatePairsScore($dinosaurs);
            break;
            
        case 4: // Trío Frondoso
            $score = ($count === 3) ? 7 : 0;
            break;
            
        case 5: // Rey de la Selva
        case 6: // Isla Solitaria
            $score = ($count === 1) ? 7 : 0;
            break;
            
        case 7: // Río
            $score = $count; // 1 punto por dinosaurio
            break;
    }
    
    return $score;
}

/**
 * Tabla de puntuación por cantidad
 */
function getScoreByCount($count) {
    $scores = [0, 1, 3, 6, 10, 15, 21];
    return $scores[$count] ?? 21;
}

/**
 * Calcular puntos por parejas
 */
function calculatePairsScore($dinosaurs) {
    $species = [];
    foreach ($dinosaurs as $dino) {
        $type = $dino['tipo'] ?? $dino;
        $species[$type] = ($species[$type] ?? 0) + 1;
    }
    
    $pairs = 0;
    foreach ($species as $count) {
        $pairs += floor($count / 2);
    }
    
    return $pairs * 5; // 5 puntos por pareja
}

/**
 * Verificar y limpiar sesiones expiradas
 */
function cleanExpiredSessions() {
    try {
        $db = Database::getInstance();
        $expiredCount = $db->delete('sesiones', 'fecha_expiracion < NOW() OR activa = 0');
        
        if ($expiredCount > 0) {
            logError("Cleaned {$expiredCount} expired sessions");
        }
    } catch (Exception $e) {
        logError('Error cleaning expired sessions: ' . $e->getMessage());
    }
}

/**
 * Obtener información del cliente
 */
function getClientInfo() {
    return [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Validar entrada de API
 */
function validateApiInput($requiredFields, $input) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $errors[] = "Campo requerido: {$field}";
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Rate limiting simple
 */
function checkRateLimit($action, $limit = 10, $window = 60) {
    $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file = LOG_PATH . 'rate_limit_' . date('Y-m-d') . '.json';
    
    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: [];
    }
    
    $now = time();
    $windowStart = $now - $window;
    
    // Limpiar entradas antiguas
    if (isset($data[$key])) {
        $data[$key] = array_filter($data[$key], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });
    }
    
    // Verificar límite
    $currentCount = count($data[$key] ?? []);
    if ($currentCount >= $limit) {
        return false;
    }
    
    // Agregar nueva entrada
    $data[$key][] = $now;
    
    // Guardar datos
    file_put_contents($file, json_encode($data), LOCK_EX);
    
    return true;
}

// ===== INICIALIZACIÓN =====

// Verificar conexión a base de datos al cargar
try {
    $testConnection = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false
        ]
    );
    
    // Verificar que las tablas principales existan
    $tables = ['usuarios', 'partidas', 'dinosaurios_tipos', 'recintos_tipos'];
    foreach ($tables as $table) {
        $stmt = $testConnection->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            throw new Exception("Tabla requerida '{$table}' no existe. Ejecute el script de instalación.");
        }
    }
    
    // Cerrar conexión de prueba
    $testConnection = null;
    
} catch (PDOException $e) {
    $errorMsg = 'Error de conexión a base de datos: ' . $e->getMessage();
    
    if (APP_ENV === 'development') {
        die($errorMsg);
    } else {
        logError($errorMsg);
        die('Error de conexión a base de datos. Por favor, contacte al administrador.');
    }
} catch (Exception $e) {
    $errorMsg = 'Error de configuración: ' . $e->getMessage();
    
    if (APP_ENV === 'development') {
        die($errorMsg);
    } else {
        logError($errorMsg);
        die('Error de configuración. Por favor, contacte al administrador.');
    }
}

// Limpiar sesiones expiradas periódicamente
if (rand(1, 100) <= 5) { // 5% de probabilidad
    cleanExpiredSessions();
}

// Configuración adicional para desarrollo
if (APP_ENV === 'development') {
    // Mostrar todos los errores en desarrollo
    ini_set('display_startup_errors', 1);
    
    // Log adicional para debugging
    define('DEBUG_MODE', true);
} else {
    define('DEBUG_MODE', false);
}

/**
 * Función de debug que solo funciona en desarrollo
 */
function debugLog($message, $data = null) {
    if (!DEBUG_MODE) return;
    
    $logFile = LOG_PATH . 'debug_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $dataStr = $data ? ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    
    $logMessage = "[{$timestamp}] DEBUG: {$message}{$dataStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Log de inicialización exitosa
debugLog('Configuration loaded successfully', [
    'app_name' => APP_NAME,
    'app_version' => APP_VERSION,
    'environment' => APP_ENV,
    'php_version' => PHP_VERSION,
    'db_host' => DB_HOST,
    'db_name' => DB_NAME
]);

?>