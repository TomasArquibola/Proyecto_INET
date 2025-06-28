<?php
include 'conexion.php'; // conexión a tu BD

$nombre = $_POST['nombre'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$fecha = date('Y-m-d');

$sql = "INSERT INTO Usuario (Nombre, Email, Contraseña, Fecha_Registro) 
        VALUES ('$nombre', '$email', '$password', '$fecha')";

if (mysqli_query($conn, $sql)) {
    header("Location: login.html");
} else {
    echo "Error al registrar: " . mysqli_error($conn);
}
?>