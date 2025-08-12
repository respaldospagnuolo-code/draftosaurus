<?php
/**
 * API de Login - Autenticación de usuarios
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

try {
    // Obtener datos del cuerpo de la petición
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    // Validar campos requeridos
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonResponse(null, 'Usuario y contraseña son requeridos', false, 400);
    }
    
    // Crear instancia del modelo User
    $user = new User();
    
    // Intentar autenticar
    if ($user->authenticate($username, $password)) {
        // Iniciar sesión
        session_name(SESSION_NAME);
        session_start();
        
        // Regenerar ID de sesión por seguridad
        session_regenerate_id(true);
        
        // Guardar datos en sesión
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['username'] = $user->getUsername();
        $_SESSION['role'] = $user->getRole();
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Guardar sesión en base de datos
        $sessionData = [
            'id' => session_id(),
            'usuario_id' => $user->getId(),
            'ip_address' => $_SESSION['ip_address'],
            'user_agent' => substr($_SESSION['user_agent'], 0, 500),
            'datos_sesion' => json_encode($_SESSION),
            'fecha_expiracion' => date('Y-m-d H:i:s', time() + SESSION_LIFETIME)
        ];
        
        $db = Database::getInstance();
        
        // Eliminar sesiones anteriores del usuario
        $db->delete('sesiones', 'usuario_id = ?', ['usuario_id' => $user->getId()]);
        
        // Crear nueva sesión
        $db->insert('sesiones', $sessionData);
        
        // Respuesta exitosa
        jsonResponse([
            'user' => $user->toArray(true), // Incluir datos privados para el usuario logueado
            'session_id' => session_id(),
            'expires_in' => SESSION_LIFETIME
        ], 'Login exitoso', true, 200);
        
    } else {
        // Credenciales incorrectas
        jsonResponse(null, 'Credenciales incorrectas', false, 401);
    }
    
} catch (Exception $e) {
    logError('Error en login: ' . $e->getMessage(), [
        'username' => $username ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    // No revelar detalles del error en producción
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>