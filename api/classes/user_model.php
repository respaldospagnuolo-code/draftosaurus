<?php
/**
 * Modelo User - Gestión de usuarios del sistema
 */

class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $passwordHash;
    private $fechaNacimiento;
    private $fechaRegistro;
    private $ultimoAcceso;
    private $role;
    private $activo;
    private $intentosLogin;
    private $bloqueadoHasta;
    private $fotoPerfil;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getEmail() { return $this->email; }
    public function getFechaNacimiento() { return $this->fechaNacimiento; }
    public function getFechaRegistro() { return $this->fechaRegistro; }
    public function getUltimoAcceso() { return $this->ultimoAcceso; }
    public function getRole() { return $this->role; }
    public function isActivo() { return (bool)$this->activo; }
    public function getIntentosLogin() { return $this->intentosLogin; }
    public function getBloqueadoHasta() { return $this->bloqueadoHasta; }
    public function getFotoPerfil() { return $this->fotoPerfil; }
    
    // Setters
    public function setUsername($username) {
        if (!$this->validateUsername($username)) {
            throw new InvalidArgumentException('Nombre de usuario no válido');
        }
        $this->username = $username;
    }
    
    public function setEmail($email) {
        if (!isValidEmail($email)) {
            throw new InvalidArgumentException('Email no válido');
        }
        $this->email = $email;
    }
    
    public function setPassword($password) {
        if (strlen($password) < MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('La contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres');
        }
        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
    }
    
    public function setFechaNacimiento($fecha) {
        $date = new DateTime($fecha);
        $now = new DateTime();
        $age = $now->diff($date)->y;
        
        if ($age < 13) {
            throw new InvalidArgumentException('Debe ser mayor de 13 años para registrarse');
        }
        
        $this->fechaNacimiento = $fecha;
    }
    
    public function setRole($role) {
        if (!in_array($role, ['admin', 'player'])) {
            throw new InvalidArgumentException('Rol no válido');
        }
        $this->role = $role;
    }
    
    public function setActivo($activo) {
        $this->activo = (bool)$activo;
    }
    
    public function setFotoPerfil($foto) {
        $this->fotoPerfil = $foto;
    }
    
    // Métodos de validación
    private function validateUsername($username) {
        return strlen($username) >= 3 && 
               strlen($username) <= MAX_USERNAME_LENGTH && 
               preg_match('/^[a-zA-Z0-9_]+$/', $username);
    }
    
    // Crear nuevo usuario
    public function create($username, $email, $password, $fechaNacimiento) {
        try {
            $this->db->beginTransaction();
            
            // Verificar si el username ya existe
            if ($this->existsByUsername($username)) {
                throw new Exception('El nombre de usuario ya está en uso');
            }
            
            // Verificar si el email ya existe
            if ($this->existsByEmail($email)) {
                throw new Exception('El email ya está registrado');
            }
            
            // Establecer propiedades
            $this->setUsername($username);
            $this->setEmail($email);
            $this->setPassword($password);
            $this->setFechaNacimiento($fechaNacimiento);
            $this->role = 'player';
            $this->activo = 1;
            $this->intentosLogin = 0;
            
            // Insertar en base de datos
            $data = [
                'username' => $this->username,
                'email' => $this->email,
                'password_hash' => $this->passwordHash,
                'fecha_nacimiento' => $this->fechaNacimiento,
                'role' => $this->role,
                'activo' => $this->activo
            ];
            
            $this->id = $this->db->insert('usuarios', $data);
            $this->fechaRegistro = date('Y-m-d H:i:s');
            
            $this->db->commit();
            
            logError("Usuario creado: {$this->username} (ID: {$this->id})");
            return $this->id;
            
        } catch (Exception $e) {
            $this->db->rollback();
            logError("Error al crear usuario: " . $e->getMessage(), ['username' => $username, 'email' => $email]);
            throw $e;
        }
    }
    
    // Cargar usuario por ID
    public function loadById($id) {
        $user = $this->db->selectOne('usuarios', '*', 'id = ?', [$id]);
        
        if (!$user) {
            return false;
        }
        
        $this->loadFromArray($user);
        return true;
    }
    
    // Cargar usuario por username
    public function loadByUsername($username) {
        $user = $this->db->selectOne('usuarios', '*', 'username = ?', [$username]);
        
        if (!$user) {
            return false;
        }
        
        $this->loadFromArray($user);
        return true;
    }
    
    // Cargar usuario por email
    public function loadByEmail($email) {
        $user = $this->db->selectOne('usuarios', '*', 'email = ?', [$email]);
        
        if (!$user) {
            return false;
        }
        
        $this->loadFromArray($user);
        return true;
    }
    
    // Cargar datos desde array
    private function loadFromArray($data) {
        $this->id = $data['id'];
        $this->username = $data['username'];
        $this->email = $data['email'];
        $this->passwordHash = $data['password_hash'];
        $this->fechaNacimiento = $data['fecha_nacimiento'];
        $this->fechaRegistro = $data['fecha_registro'];
        $this->ultimoAcceso = $data['ultimo_acceso'];
        $this->role = $data['role'];
        $this->activo = $data['activo'];
        $this->intentosLogin = $data['intentos_login'];
        $this->bloqueadoHasta = $data['bloqueado_hasta'];
        $this->fotoPerfil = $data['foto_perfil'];
    }
    
    // Verificar contraseña
    public function verifyPassword($password) {
        return password_verify($password, $this->passwordHash);
    }
    
    // Autenticar usuario
    public function authenticate($usernameOrEmail, $password) {
        // Cargar usuario por username o email
        $loaded = $this->loadByUsername($usernameOrEmail) || $this->loadByEmail($usernameOrEmail);
        
        if (!$loaded) {
            logError("Intento de login con usuario inexistente: $usernameOrEmail");
            return false;
        }
        
        // Verificar si el usuario está activo
        if (!$this->activo) {
            logError("Intento de login con usuario inactivo: {$this->username}");
            throw new Exception('Usuario inactivo');
        }
        
        // Verificar si está bloqueado
        if ($this->isBlocked()) {
            $timeLeft = strtotime($this->bloqueadoHasta) - time();
            $minutesLeft = ceil($timeLeft / 60);
            throw new Exception("Usuario bloqueado. Inténtalo en $minutesLeft minutos");
        }
        
        // Verificar contraseña
        if (!$this->verifyPassword($password)) {
            $this->incrementLoginAttempts();
            logError("Contraseña incorrecta para usuario: {$this->username}");
            return false;
        }
        
        // Login exitoso
        $this->resetLoginAttempts();
        $this->updateLastAccess();
        
        logError("Login exitoso: {$this->username}");
        return true;
    }
    
    // Verificar si el usuario está bloqueado
    public function isBlocked() {
        if (!$this->bloqueadoHasta) {
            return false;
        }
        
        if (strtotime($this->bloqueadoHasta) > time()) {
            return true;
        }
        
        // El bloqueo ha expirado, limpiarlo
        $this->resetLoginAttempts();
        return false;
    }
    
    // Incrementar intentos de login
    private function incrementLoginAttempts() {
        $this->intentosLogin++;
        
        $updateData = ['intentos_login' => $this->intentosLogin];
        
        // Si excede el máximo, bloquear usuario
        if ($this->intentosLogin >= MAX_LOGIN_ATTEMPTS) {
            $this->bloqueadoHasta = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME);
            $updateData['bloqueado_hasta'] = $this->bloqueadoHasta;
            logError("Usuario bloqueado por exceso de intentos: {$this->username}");
        }
        
        $this->db->update('usuarios', $updateData, 'id = ?', ['id' => $this->id]);
    }
    
    // Resetear intentos de login
    private function resetLoginAttempts() {
        $this->intentosLogin = 0;
        $this->bloqueadoHasta = null;
        
        $this->db->update('usuarios', [
            'intentos_login' => 0,
            'bloqueado_hasta' => null
        ], 'id = ?', ['id' => $this->id]);
    }
    
    // Actualizar último acceso
    private function updateLastAccess() {
        $this->ultimoAcceso = date('Y-m-d H:i:s');
        
        $this->db->update('usuarios', [
            'ultimo_acceso' => $this->ultimoAcceso
        ], 'id = ?', ['id' => $this->id]);
    }
    
    // Actualizar información del usuario
    public function update() {
        if (!$this->id) {
            throw new Exception('No se puede actualizar un usuario que no existe');
        }
        
        $data = [
            'username' => $this->username,
            'email' => $this->email,
            'fecha_nacimiento' => $this->fechaNacimiento,
            'role' => $this->role,
            'activo' => $this->activo,
            'foto_perfil' => $this->fotoPerfil
        ];
        
        // Si hay nueva contraseña, incluirla
        if ($this->passwordHash) {
            $data['password_hash'] = $this->passwordHash;
        }
        
        $affected = $this->db->update('usuarios', $data, 'id = ?', ['id' => $this->id]);
        
        logError("Usuario actualizado: {$this->username} (ID: {$this->id})");
        return $affected > 0;
    }
    
    // Cambiar contraseña
    public function changePassword($currentPassword, $newPassword) {
        if (!$this->verifyPassword($currentPassword)) {
            throw new Exception('Contraseña actual incorrecta');
        }
        
        $this->setPassword($newPassword);
        
        $affected = $this->db->update('usuarios', [
            'password_hash' => $this->passwordHash
        ], 'id = ?', ['id' => $this->id]);
        
        logError("Contraseña cambiada para usuario: {$this->username}");
        return $affected > 0;
    }
    
    // Eliminar usuario (soft delete)
    public function delete() {
        if (!$this->id) {
            throw new Exception('No se puede eliminar un usuario que no existe');
        }
        
        $affected = $this->db->update('usuarios', [
            'activo' => 0
        ], 'id = ?', ['id' => $this->id]);
        
        $this->activo = 0;
        
        logError("Usuario eliminado (soft delete): {$this->username} (ID: {$this->id})");
        return $affected > 0;
    }
    
    // Verificar si existe por username
    public function existsByUsername($username) {
        return $this->db->exists('usuarios', 'username = ?', [$username]);
    }
    
    // Verificar si existe por email
    public function existsByEmail($email) {
        return $this->db->exists('usuarios', 'email = ?', [$email]);
    }
    
    // Obtener estadísticas del usuario
    public function getStats() {
        if (!$this->id) {
            return null;
        }
        
        $stats = $this->db->selectOne('usuario_estadisticas', '*', 'usuario_id = ?', [$this->id]);
        
        if (!$stats) {
            // Crear estadísticas iniciales si no existen
            $this->db->insert('usuario_estadisticas', ['usuario_id' => $this->id]);
            return [
                'partidas_jugadas' => 0,
                'partidas_ganadas' => 0,
                'partidas_perdidas' => 0,
                'puntuacion_maxima' => 0,
                'puntuacion_promedio' => 0.00,
                'tiempo_juego_total' => 0,
                'racha_victorias_actual' => 0,
                'racha_victorias_maxima' => 0
            ];
        }
        
        return $stats;
    }
    
    // Obtener ranking del usuario
    public function getRanking() {
        if (!$this->id) {
            return null;
        }
        
        $sql = "
            SELECT COUNT(*) + 1 as ranking 
            FROM usuario_estadisticas us1 
            INNER JOIN usuarios u ON us1.usuario_id = u.id 
            WHERE u.activo = 1 
            AND us1.puntuacion_maxima > (
                SELECT COALESCE(us2.puntuacion_maxima, 0) 
                FROM usuario_estadisticas us2 
                WHERE us2.usuario_id = ?
            )
        ";
        
        $result = $this->db->query($sql, [$this->id]);
        return $result[0]['ranking'] ?? null;
    }
    
    // Listar usuarios con paginación
    public static function getAll($page = 1, $limit = 20, $filters = []) {
        $db = Database::getInstance();
        $offset = ($page - 1) * $limit;
        
        $where = ['activo = 1'];
        $params = [];
        
        // Aplicar filtros
        if (!empty($filters['role'])) {
            $where[] = 'role = ?';
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(username LIKE ? OR email LIKE ?)';
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Obtener total
        $total = $db->count('usuarios', $whereClause, $params);
        
        // Obtener usuarios
        $sql = "
            SELECT u.*, us.partidas_jugadas, us.partidas_ganadas, us.puntuacion_maxima 
            FROM usuarios u 
            LEFT JOIN usuario_estadisticas us ON u.id = us.usuario_id 
            WHERE $whereClause 
            ORDER BY u.fecha_registro DESC 
            LIMIT $limit OFFSET $offset
        ";
        
        $users = $db->query($sql, $params);
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
    
    // Obtener ranking global
    public static function getGlobalRanking($limit = 10) {
        $db = Database::getInstance();
        
        $sql = "
            SELECT u.id, u.username, u.foto_perfil,
                   COALESCE(us.partidas_jugadas, 0) as partidas_jugadas,
                   COALESCE(us.partidas_ganadas, 0) as partidas_ganadas,
                   COALESCE(us.puntuacion_maxima, 0) as puntuacion_maxima,
                   COALESCE(us.puntuacion_promedio, 0) as puntuacion_promedio,
                   CASE 
                       WHEN us.partidas_jugadas > 0 THEN ROUND((us.partidas_ganadas / us.partidas_jugadas) * 100, 2)
                       ELSE 0 
                   END as porcentaje_victorias
            FROM usuarios u
            LEFT JOIN usuario_estadisticas us ON u.id = us.usuario_id
            WHERE u.activo = 1 AND u.role = 'player'
            ORDER BY us.puntuacion_maxima DESC, us.partidas_ganadas DESC
            LIMIT ?
        ";
        
        return $db->query($sql, [$limit]);
    }
    
    // Convertir a array para API
    public function toArray($includePrivate = false) {
        $data = [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $includePrivate ? $this->email : null,
            'fecha_nacimiento' => $includePrivate ? $this->fechaNacimiento : null,
            'fecha_registro' => $this->fechaRegistro,
            'ultimo_acceso' => $includePrivate ? $this->ultimoAcceso : null,
            'role' => $this->role,
            'activo' => $this->activo,
            'foto_perfil' => $this->fotoPerfil
        ];
        
        // Remover campos null para usuarios públicos
        if (!$includePrivate) {
            $data = array_filter($data, function($value) {
                return $value !== null;
            });
        }
        
        return $data;
    }
}
?>