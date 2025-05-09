<?php

class Database {
    private static $instance = null; // Instancia única
    private $conn;
    private $host = 'localhost';
    private $db_name = 'api_medical';
    private $username = 'root';
    private $password = '';

    
    private function __construct() {}

    public static function getInstance() {
        // Si no existe la instancia, crearla
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host={$this->host};dbname={$this->db_name}", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            echo "Error de conexión: " . $e->getMessage();
        }
        return $this->conn;
    }
}
