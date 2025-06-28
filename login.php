<?php
include 'conexion.php';

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM Usuario WHERE Email = '$email'";
$result = mysqli_query($conn, $sql);

if ($user = mysqli_fetch_assoc($result)) {
    if (password_verify($password, $user['Contraseña'])) {
        session_start();
        $_SESSION['usuario'] = $user['ID_Usuario'];
        header("Location: index.php");
    } else {
        echo "Contraseña incorrecta.";
    }
} else {
    echo "Usuario no encontrado.";
}
?>