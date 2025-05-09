<?php

// Cargar el autoloader de Composer
require_once __DIR__ . '/../vendor/autoload.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';

// Configuración para las pruebas
error_reporting(E_ALL);
ini_set('display_errors', 1);
