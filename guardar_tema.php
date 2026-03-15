<?php
session_start();
include("config/conexion.php");

if (!isset($_SESSION['usuario'])) {
  http_response_code(401); // No autorizado
  echo "Acceso denegado";
  exit;
}

if (!isset($_POST['tema'])) {
  http_response_code(400); // Solicitud incorrecta
  echo "Tema no proporcionado";
  exit;
}

$tema = $_POST['tema'];
$usuario = $_SESSION['usuario'];

// Validar que solo se permiten "oscuro" o "claro"
if ($tema !== 'oscuro' && $tema !== 'claro') {
  http_response_code(400);
  echo "Valor de tema inválido";
  exit;
}

// Actualizar en base de datos
$stmt = $conexion->prepare("UPDATE usuarios SET tema = ? WHERE usuario = ?");
$stmt->execute([$tema, $usuario]);

// Guardar en sesión también
$_SESSION['tema'] = $tema;

echo "Preferencia de tema actualizada a '$tema'";
?>
