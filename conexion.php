<?php
$host = "localhost";
$usuario = "root"; // por defecto en localhost
$contrasena = "";  // en XAMPP normalmente sin contraseña
$base_datos = "booking_app";

// Crear conexión
$conn = new mysqli($host, $usuario, $contrasena, $base_datos);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Opcional: configurar charset
$conn->set_charset("utf8mb4");
?>
