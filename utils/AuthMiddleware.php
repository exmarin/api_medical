<?php

class AuthMiddleware {
    private $db;
    public $currentUser;

    public function __construct($db) {
        $this->db = $db;
    }

    // Función para autenticar el usuario mediante el token
    public function authenticate($token) {
        if (empty($token)) {
            echo json_encode(['message' => 'Token is required']);
            return null;
        }

        // Buscar al usuario en la base de datos por el token
        $query = 'SELECT * FROM users WHERE token = :token';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        // Si el token es válido, retornar los datos del usuario
        if ($stmt->rowCount() > 0) {
            $this->currentUser = $stmt->fetch(PDO::FETCH_ASSOC); // Obtener al usuario
            return $this->currentUser; // Retornar el usuario autenticado
        } else {
            // Si el token no es válido
            echo json_encode(['message' => 'Invalid token']);
            return null;
        }
    }
}
