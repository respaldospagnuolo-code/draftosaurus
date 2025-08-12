<?php
/**
 * API de Sesión - Verificar sesión activa
 */

require_once '../config/config.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

try {
    session_name(SESSION_NAME);
    session_start();
    
    $sessionId = session_id();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        jsonResponse(null, 'No hay sesión activa', false, 401);
    }
    
    // Verificar sesión en base de datos
    $db = Database::getInstance();
    $session = $db->selectOne('sesiones', '*', 'id = ? AND usuario_id = ? AND activa = 1', [
        'id' => $sessionId,
        'usuario_id' => $userId
    ]);
    
    if (!$session) {
        // Sesión no válida en BD, limpiar sesión local
        session_destroy();
        jsonResponse(null, 'Sesión no válida', false, 401);
    }
    
    // Verificar si la sesión ha expirado
    if (strtotime($session['fecha_expiracion']) < time()) {
        // Sesión expirada, eliminar de BD y limpiar local
        $db->delete('sesiones', 'id = ?', ['id' => $sessionId]);
        session_destroy();
        jsonResponse(null, 'Sesión expirada', false, 401);
    }
    
    // Cargar datos del usuario
    $user = new User();
    if (!$user->loadById($userId)) {
        // Usuario no existe, eliminar sesión
        $db->delete('sesiones', 'id = ?', ['id' => $sessionId]);
        session_destroy();
        jsonResponse(null, 'Usuario no válido', false, 401);
    }
    
    // Verificar si el usuario está activo
    if (!$user->isActivo()) {
        // Usuario inactivo, eliminar sesión
        $db->delete('sesiones', 'id = ?', ['id' => $sessionId]);
        session_destroy();
        jsonResponse(null, 'Usuario inactivo', false, 401);
    }
    
    // Actualizar tiempo de última actividad en la sesión
    $db->update('sesiones', [
        'fecha_actualizacion' => date('Y-m-d H:i:s')
    ], 'id = ?', ['id' => $sessionId]);
    
    // Extender expiración si la sesión está por vencer (menos de 1 hora restante)
    $timeLeft = strtotime($session['fecha_expiracion']) - time();
    if ($timeLeft < 3600) { // Menos de 1 hora
        $newExpiration = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $db->update('sesiones', [
            'fecha_expiracion' => $newExpiration
        ], 'id = ?', ['id' => $sessionId]);
        
        $timeLeft = SESSION_LIFETIME;
    }
    
    // Respuesta con datos de la sesión
    jsonResponse([
        'user' => $user->toArray(true),
        'session' => [
            'id' => $sessionId,
            'login_time' => $_SESSION['login_time'] ?? null,
            'expires_in' => $timeLeft,
            'ip_address' => $session['ip_address']
        ]
    ], 'Sesión válida', true, 200);
    
} catch (Exception $e) {
    logError('Error verificando sesión: ' . $e->getMessage(), [
        'session_id' => $sessionId ?? 'unknown',
        'user_id' => $userId ?? 'unknown'
    ]);
    
    jsonResponse(null, 'Error verificando sesión', false, 500);
}
?>