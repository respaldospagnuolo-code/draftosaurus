<?php
/**
 * API para guardar estado de partida
 * /api/game/save.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
checkUserPermission('player');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['gameId']) || !isset($input['state'])) {
        jsonResponse(null, 'Datos incompletos', false, 400);
    }
    
    $gameId = (int)$input['gameId'];
    $gameState = $input['state'];
    
    $db = Database::getInstance();
    
    // Verificar que la partida existe y el usuario puede modificarla
    $game = $db->selectOne('partidas', '*', 'id = ?', [$gameId]);
    
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada', false, 404);
    }
    
    // Verificar que el usuario está en la partida
    $playerInGame = $db->selectOne('partida_jugadores', '*', 
                                  'partida_id = ? AND usuario_id = ?',
                                  [$gameId, $_SESSION['user_id']]);
    
    if (!$playerInGame) {
        jsonResponse(null, 'No tienes permisos para guardar esta partida', false, 403);
    }
    
    // Actualizar configuración de la partida
    $config = json_decode($game['configuracion'], true) ?: [];
    $config['saved_state'] = $gameState;
    $config['last_save'] = date('Y-m-d H:i:s');
    
    $db->update('partidas', [
        'configuracion' => json_encode($config),
        'ronda_actual' => $gameState['currentRound'] ?? 1,
        'turno_actual' => $gameState['currentTurn'] ?? 1,
        'jugador_activo' => $gameState['currentPlayer'] ?? 0
    ], 'id = ?', [$gameId]);
    
    // Log del guardado
    $logData = [
        'partida_id' => $gameId,
        'jugador_id' => $playerInGame['id'],
        'accion' => 'guardar_estado',
        'descripcion' => 'Estado de partida guardado automáticamente',
        'datos_adicionales' => json_encode([
            'round' => $gameState['currentRound'] ?? 1,
            'turn' => $gameState['currentTurn'] ?? 1,
            'moves_count' => count($gameState['gameHistory'] ?? [])
        ])
    ];
    
    $db->insert('logs_partida', $logData);
    
    jsonResponse(null, 'Estado guardado correctamente', true);
    
} catch (Exception $e) {
    logError('Error guardando estado: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'game_id' => $gameId ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>

<?php
/**
 * API para cargar estado de partida
 * /api/game/load.php
 */

require_once '../config/config.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
checkUserPermission('player');

try {
    $gameId = (int)($_GET['gameId'] ?? 0);
    
    if (!$gameId) {
        jsonResponse(null, 'ID de partida requerido', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Verificar que la partida existe y el usuario puede acceder
    $game = $db->selectOne('partidas', '*', 'id = ?', [$gameId]);
    
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada', false, 404);
    }
    
    // Verificar que el usuario está en la partida
    $playerInGame = $db->selectOne('partida_jugadores', '*', 
                                  'partida_id = ? AND usuario_id = ?',
                                  [$gameId, $_SESSION['user_id']]);
    
    if (!$playerInGame) {
        jsonResponse(null, 'No tienes permisos para cargar esta partida', false, 403);
    }
    
    // Obtener configuración guardada
    $config = json_decode($game['configuracion'], true) ?: [];
    $savedState = $config['saved_state'] ?? null;
    
    if (!$savedState) {
        jsonResponse(null, 'No hay estado guardado para esta partida', false, 404);
    }
    
    // Log de la carga
    $logData = [
        'partida_id' => $gameId,
        'jugador_id' => $playerInGame['id'],
        'accion' => 'cargar_estado',
        'descripcion' => 'Estado de partida cargado',
        'datos_adicionales' => json_encode([
            'last_save' => $config['last_save'] ?? null
        ])
    ];
    
    $db->insert('logs_partida', $logData);
    
    jsonResponse($savedState, 'Estado cargado correctamente', true);
    
} catch (Exception $e) {
    logError('Error cargando estado: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'game_id' => $gameId ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>

<?php
/**
 * API de Analytics de Sesión
 * /api/analytics/session.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión (opcional - también funciona para usuarios no logueados)
session_name(SESSION_NAME);
session_start();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Preparar datos de analytics
    $analyticsData = [
        'usuario_id' => $_SESSION['user_id'] ?? null,
        'session_id' => session_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'session_duration' => (int)($input['sessionDuration'] ?? 0),
        'moves_count' => (int)(count($input['moves'] ?? [])),
        'errors_count' => (int)($input['errors'] ?? 0),
        'undos_count' => (int)($input['undos'] ?? 0),
        'dice_rolls_count' => (int)(count($input['diceRolls'] ?? [])),
        'average_time_per_move' => (int)($input['averageTimePerMove'] ?? 0),
        'most_used_enclosure' => $input['mostUsedEnclosure']['key'] ?? null,
        'most_used_dinosaur' => $input['mostUsedDinosaur']['key'] ?? null,
        'fecha_registro' => date('Y-m-d H:i:s'),
        'datos_detallados' => json_encode($input)
    ];
    
    // Crear tabla de analytics si no existe
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS analytics_sesiones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(500),
            session_duration INT NOT NULL,
            moves_count INT NOT NULL,
            errors_count INT NOT NULL,
            undos_count INT NOT NULL,
            dice_rolls_count INT NOT NULL,
            average_time_per_move INT NOT NULL,
            most_used_enclosure VARCHAR(50),
            most_used_dinosaur VARCHAR(50),
            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
            datos_detallados JSON,
            INDEX idx_usuario (usuario_id),
            INDEX idx_fecha (fecha_registro),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->getConnection()->exec($createTableSQL);
    
    // Insertar datos de analytics
    $db->insert('analytics_sesiones', $analyticsData);
    
    jsonResponse(null, 'Analytics guardados correctamente', true);
    
} catch (Exception $e) {
    logError('Error guardando analytics: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'session_id' => session_id()
    ]);
    
    // No devolver error al usuario para no afectar la experiencia
    jsonResponse(null, 'Analytics procesados', true);
}
?>