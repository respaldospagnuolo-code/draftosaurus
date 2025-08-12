<?php
/**
 * API de Registro - Creación de nuevos usuarios
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
    
    // Validar y sanitizar campos
    $username = sanitizeInput($input['username'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $birthdate = sanitizeInput($input['birthdate'] ?? '');
    
    // Validaciones básicas
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'El nombre de usuario es requerido';
    } elseif (strlen($username) < 3 || strlen($username) > MAX_USERNAME_LENGTH) {
        $errors[] = 'El nombre de usuario debe tener entre 3 y ' . MAX_USERNAME_LENGTH . ' caracteres';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'El nombre de usuario solo puede contener letras, números y guiones bajos';
    }
    
    if (empty($email)) {
        $errors[] = 'El email es requerido';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'El formato del email no es válido';
    }
    
    if (empty($password)) {
        $errors[] = 'La contraseña es requerida';
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'La contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres';
    }
    
    if (empty($birthdate)) {
        $errors[] = 'La fecha de nacimiento es requerida';
    } else {
        // Validar edad mínima
        $birthDateTime = new DateTime($birthdate);
        $now = new DateTime();
        $age = $now->diff($birthDateTime)->y;
        
        if ($age < 13) {
            $errors[] = 'Debes ser mayor de 13 años para registrarte';
        }
        
        if ($age > 120) {
            $errors[] = 'Fecha de nacimiento no válida';
        }
    }
    
    // Si hay errores de validación, retornarlos
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], implode(', ', $errors), false, 400);
    }
    
    // Crear usuario
    $user = new User();
    
    try {
        $userId = $user->create($username, $email, $password, $birthdate);
        
        // Cargar el usuario creado
        $user->loadById($userId);
        
        // Iniciar sesión automáticamente
        session_name(SESSION_NAME);
        session_start();
        session_regenerate_id(true);
        
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
        $db->insert('sesiones', $sessionData);
        
        // Respuesta exitosa
        jsonResponse([
            'user' => $user->toArray(true),
            'session_id' => session_id(),
            'expires_in' => SESSION_LIFETIME
        ], 'Usuario registrado exitosamente', true, 201);
        
    } catch (Exception $e) {
        // Manejar errores específicos del modelo
        if (strpos($e->getMessage(), 'ya está en uso') !== false || 
            strpos($e->getMessage(), 'ya está registrado') !== false) {
            jsonResponse(null, $e->getMessage(), false, 409); // Conflict
        } else {
            throw $e; // Re-lanzar otros errores
        }
    }
    
} catch (Exception $e) {
    logError('Error en registro: ' . $e->getMessage(), [
        'username' => $username ?? 'unknown',
        'email' => $email ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>