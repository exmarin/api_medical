<?php
//Probar conexión a la base de datos
require_once 'config/database.php';
$database = new Database();
$conn = $database->connect();
if ($conn) {
    echo "Conexión exitosa a la base de datos.";
} else {
    echo "Error al conectar a la base de datos.";
}

