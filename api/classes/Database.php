<?php
/**
 * Clase Database - Gestión de conexión y operaciones de base de datos
 * Implementa patrón Singleton para conexión única
 */

class Database {
    private static $instance = null;
    private $connection;
    private $stmt;
    
    // Constructor privado para patrón Singleton
    private function __construct() {
        try {
            $this->connection = new PDO(
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
        } catch (PDOException $e) {
            logError('Database connection error: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    // Obtener instancia única (Singleton)
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Prevenir clonación
    private function __clone() {}
    
    // Prevenir deserialización
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    // Obtener conexión PDO
    public function getConnection() {
        return $this->connection;
    }
    
    // Preparar consulta
    public function prepare($sql) {
        try {
            $this->stmt = $this->connection->prepare($sql);
            if (LOG_QUERIES) {
                logError("SQL Query prepared: " . $sql);
            }
            return $this->stmt;
        } catch (PDOException $e) {
            logError('Prepare error: ' . $e->getMessage(), ['sql' => $sql]);
            throw new Exception('Database prepare error');
        }
    }
    
    // Ejecutar consulta preparada
    public function execute($params = []) {
        try {
            $result = $this->stmt->execute($params);
            if (LOG_QUERIES && !empty($params)) {
                logError("SQL Query executed with params: " . json_encode($params));
            }
            return $result;
        } catch (PDOException $e) {
            logError('Execute error: ' . $e->getMessage(), ['params' => $params]);
            throw new Exception('Database execute error');
        }
    }
    
    // Obtener múltiples filas
    public function fetchAll() {
        return $this->stmt->fetchAll();
    }
    
    // Obtener una fila
    public function fetch() {
        return $this->stmt->fetch();
    }
    
    // Obtener número de filas afectadas
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    // Obtener último ID insertado
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Iniciar transacción
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Confirmar transacción
    public function commit() {
        return $this->connection->commit();
    }
    
    // Revertir transacción
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    // Verificar si estamos en una transacción
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
    
    // Métodos de conveniencia para operaciones comunes
    
    /**
     * Insertar registro
     * @param string $table Nombre de la tabla
     * @param array $data Datos a insertar [columna => valor]
     * @return int ID del registro insertado
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $this->prepare($sql);
        $this->execute($data);
        
        return $this->lastInsertId();
    }
    
    /**
     * Actualizar registro
     * @param string $table Nombre de la tabla
     * @param array $data Datos a actualizar [columna => valor]
     * @param string $where Condición WHERE
     * @param array $whereParams Parámetros para la condición WHERE
     * @return int Número de filas afectadas
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge($data, $whereParams);
        
        $this->prepare($sql);
        $this->execute($params);
        
        return $this->rowCount();
    }
    
    /**
     * Eliminar registro
     * @param string $table Nombre de la tabla
     * @param string $where Condición WHERE
     * @param array $params Parámetros para la condición WHERE
     * @return int Número de filas afectadas
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        $this->prepare($sql);
        $this->execute($params);
        
        return $this->rowCount();
    }
    
    /**
     * Seleccionar registros
     * @param string $table Nombre de la tabla
     * @param string $columns Columnas a seleccionar
     * @param string $where Condición WHERE (opcional)
     * @param array $params Parámetros para la condición WHERE
     * @param string $orderBy Ordenamiento (opcional)
     * @param string $limit Límite (opcional)
     * @return array Resultados
     */
    public function select($table, $columns = '*', $where = '', $params = [], $orderBy = '', $limit = '') {
        $sql = "SELECT {$columns} FROM {$table}";
        
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if (!empty($limit)) {
            $sql .= " LIMIT {$limit}";
        }
        
        $this->prepare($sql);
        $this->execute($params);
        
        return $this->fetchAll();
    }
    
    /**
     * Seleccionar un solo registro
     * @param string $table Nombre de la tabla
     * @param string $columns Columnas a seleccionar
     * @param string $where Condición WHERE
     * @param array $params Parámetros para la condición WHERE
     * @return array|false Resultado o false si no encuentra
     */
    public function selectOne($table, $columns = '*', $where = '', $params = []) {
        $sql = "SELECT {$columns} FROM {$table}";
        
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        $sql .= " LIMIT 1";
        
        $this->prepare($sql);
        $this->execute($params);
        
        return $this->fetch();
    }
    
    /**
     * Contar registros
     * @param string $table Nombre de la tabla
     * @param string $where Condición WHERE (opcional)
     * @param array $params Parámetros para la condición WHERE
     * @return int Número de registros
     */
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        $this->prepare($sql);
        $this->execute($params);
        
        $result = $this->fetch();
        return (int) $result['total'];
    }
    
    /**
     * Verificar si existe un registro
     * @param string $table Nombre de la tabla
     * @param string $where Condición WHERE
     * @param array $params Parámetros para la condición WHERE
     * @return bool True si existe, false si no
     */
    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }
    
    /**
     * Ejecutar consulta SQL personalizada
     * @param string $sql Consulta SQL
     * @param array $params Parámetros
     * @return array Resultados
     */
    public function query($sql, $params = []) {
        $this->prepare($sql);
        $this->execute($params);
        return $this->fetchAll();
    }
    
    /**
     * Escapar cadena para uso en SQL
     * @param string $string Cadena a escapar
     * @return string Cadena escapada
     */
    public function quote($string) {
        return $this->connection->quote($string);
    }
}
?>