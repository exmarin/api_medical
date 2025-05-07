<?php
// Cargar los archivos necesarios
require_once 'config/database.php';
require_once 'controllers/AuthController.php';
// require_once 'controllers/AppointmentController.php';
// require_once 'controllers/PaymentController.php';

// Establecer el tipo de contenido
header('Content-Type: application/json');

// Inicializar la conexión con la base de datos
$database = new Database();
$conn = $database->connect();

// Capturar el método HTTP y el endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Probar la conexión a la base de datos
if ($conn) {
    // Conexión exitosa, proceder a las rutas
    if ($method === 'POST' && $endpoint === 'register') {
        // Registro de usuario (usamos AuthController)
        $data = json_decode(file_get_contents("php://input"));
        $authController = new AuthController($conn);
        $authController->register($data);

    } else if ($method === 'POST' && $endpoint === 'login') {
        // Login de usuario (usamos AuthController)
        $data = json_decode(file_get_contents("php://input"));
        $authController = new AuthController($conn);
        $authController->login($data);
    } else {
        // Si no se encuentra el endpoint
        echo json_encode(['message' => '🚀 La API está funcionando. Endpoint no encontrado.']);
    }

} else {
    echo json_encode(['message' => 'Error al conectar a la base de datos.']);
}
