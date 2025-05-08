<?php

class AuthController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    // Registro de usuario
    public function register($data)
    {
        if (empty($data->name)) {
            echo json_encode(['message' => 'El nombre es requerido.']);
            return;
        }

        if (empty($data->email)) {
            echo json_encode(['message' => 'El correo electrónico es requerido.']);
            return;
        }

        if (empty($data->password)) {
            echo json_encode(['message' => 'La contraseña es requerida.']);
            return;
        }

        if (empty($data->role)) {
            echo json_encode(['message' => 'El rol es requerido.']);
            return;
        }

        // Validación de la contraseña (debe tener al menos 8 caracteres, una mayúscula, un número y un carácter especial)
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_])[A-Za-z\d\W_]{8,}$/', $data->password)) {
            echo json_encode(['message' => 'La contraseña debe tener al menos 8 caracteres, una letra mayúscula, un número y un carácter especial.']);
            return;
        }

        $query = 'SELECT * FROM users WHERE email = :email';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'El correo electrónico ya está registrado']);
            return;
        }

        $query = 'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)';
        $stmt = $this->db->prepare($query);

        // Verificación de si el query fue preparado correctamente
        if ($stmt === false) {
            echo json_encode(['message' => 'Failed to prepare the statement']);
            return;
        }

        // Hashear la contraseña
        $hashed_password = password_hash($data->password, PASSWORD_BCRYPT);
        $stmt->bindParam(':name', $data->name);
        $stmt->bindParam(':email', $data->email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $data->role);

        // Ejecutar la inserción
        if ($stmt->execute()) {
            echo json_encode(['message' => 'Usuario registrado con éxito']);
        } else {
            echo json_encode(['message' => 'usuario no registrado']);
        }
    }

    // Login de usuario
    public function login($data)
    {
        // Verificar si el email y la contraseña están presentes
        if (empty($data->email)) {
            echo json_encode(['message' => 'El email es obligatorio.']);
            return;
        }
        if (empty($data->password)) {
            echo json_encode(['message' => 'La contraseña es obligatoria.']);
            return;
        }

        // Consultar si el usuario existe en la base de datos
        $query = 'SELECT * FROM users WHERE email = :email';
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $data->email);
        $stmt->execute();

        // Verificar si no se encontró al usuario
        if ($stmt->rowCount() === 0) {
            echo json_encode(['message' => 'Usuario no encontrado']);
            return;
        }

        // Recuperar los datos del usuario
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar si la contraseña es correcta
        if (password_verify($data->password, $user['password'])) {
            // Generar el token (usamos bin2hex para generar un token simulado)
            $token = bin2hex(random_bytes(32));

            // Actualizar el token del usuario en la base de datos
            $query = 'UPDATE users SET token = :token WHERE id = :id';
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();

            // Devolver el mensaje de éxito y los datos del usuario
            echo json_encode([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'token' => $token
                ]
            ]);
        } else {
            // Si las credenciales son incorrectas
            echo json_encode(['message' => 'Contraseña incorrecta']);
        }
    }
}
