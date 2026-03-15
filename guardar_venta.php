<?php
session_start();

require_once 'db.php'; // Asumimos que db.php devuelve una conexión PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    // Consulta preparada para obtener los datos del usuario
    $sql = "SELECT id, nombre, password FROM usuarios WHERE usuario = :usuario";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario' => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Iniciar sesión
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        header("Location: panel.php");
        exit();
    } else {
        echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href='login.php';</script>";
    }
}
?>