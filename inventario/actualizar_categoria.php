<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("../config/conexion.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');

    if (empty($id) || empty($nombre)) {
        header("Location: categorias.php?error=faltan_datos");
        exit;
    }

    try {
        $stmt = $conexion->prepare("UPDATE categorias SET nombre = ? WHERE id = ?");
        $stmt->execute([$nombre, $id]);
        header("Location: categorias.php?exito=actualizado");
        exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            header("Location: categorias.php?error=duplicado");
        } else {
            header("Location: categorias.php?error=db_error");
        }
        exit;
    }
} else {
    header("Location: categorias.php");
    exit;
}
?>
