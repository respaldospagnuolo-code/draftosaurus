<?php
/**
 * API de Logout - Cerrar sesión de usuario
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

try {
    session_name(SESSION_NAME);
    session_start();
    
    $sessionId = session_id();
    $userId = $_SESSION['user_id'] ?? null;
    
    // Eliminar sesión de la base de datos
    if ($sessionId) {
        $db = Database::getInstance();
        $db->delete('sesiones', 'id = ?', ['id' => $sessionId]);
    }
    
    // Limpiar variables de sesión
    $_SESSION = [];
    
    // Eliminar cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir sesión
    session_destroy();
    
    if ($userId) {
        logError("Logout exitoso para usuario ID: $userId");
    }
    
    jsonResponse(null, 'Sesión cerrada correctamente', true, 200);
    
} catch (Exception $e) {
    logError('Error en logout: ' . $e->getMessage(), [
        'session_id' => $sessionId ?? 'unknown',
        'user_id' => $userId ?? 'unknown'
    ]);
    
    // Aunque haya error, intentar cerrar la sesión local
    session_destroy();
    
    jsonResponse(null, 'Sesión cerrada', true, 200);
}
?>