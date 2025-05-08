<?php

require_once '../config/database.php';
require_once '../controllers/AuthController.php';
require_once '../controllers/AppointmentController.php';
require_once '../utils/AuthMiddleware.php';

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
    } else if ($method === 'POST' && $endpoint === 'appointments') {
        // Verifica que el token esté presente y que el usuario esté autenticado
        $token = $_GET['token'] ?? null;
        $authMiddleware = new AuthMiddleware($conn); // Instanciamos el middleware
        $user = $authMiddleware->authenticate($token); // Esto debería retornar el usuario autenticado

        if ($user) {
            // Crear cita (usamos AppointmentController)
            $data = json_decode(file_get_contents("php://input"));
            $appointmentController = new AppointmentController($conn, $user); // Pasamos el objeto usuario aquí
            $appointmentController->createAppointment($data);
        } else {
            echo json_encode(['message' => 'Unauthorized']);
        }
    } else if ($method === 'GET' && $endpoint === 'appointments') {
        // Verifica que el token esté presente y que el usuario esté autenticado
        $token = $_GET['token'] ?? null;
        $authMiddleware = new AuthMiddleware($conn); // Instanciamos el middleware
        $user = $authMiddleware->authenticate($token); // Esto debería retornar el usuario autenticado

        if ($user) {
            // Ver citas (usamos AppointmentController)
            $appointmentController = new AppointmentController($conn, $user); // Pasamos el objeto usuario aquí
            $appointmentController->getAppointments();
        } else {
            echo json_encode(['message' => 'Unauthorized']);
        }
    } else if ($method === 'GET' && $endpoint === 'todays_appointments') {
        // Verifica que el token esté presente y que el usuario esté autenticado
        $token = $_GET['token'] ?? null;
        $authMiddleware = new AuthMiddleware($conn);
        $user = $authMiddleware->authenticate($token); // Esto debería retornar el usuario autenticado

        if ($user) {
            // Listar citas del día (usamos AppointmentController)
            $appointmentController = new AppointmentController($conn, $user);
            $appointmentController->getTodaysAppointments();
        } else {
            echo json_encode(['message' => 'Unauthorized']);
        }
    } else {
        // Si no se encuentra el endpoint
        echo json_encode(['message' => '🚀 La API está funcionando. Endpoint no encontrado.']);
    }
} else {
    echo json_encode(['message' => 'Error al conectar a la base de datos.']);
}
