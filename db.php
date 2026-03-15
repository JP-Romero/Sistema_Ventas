<?php

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host     = $_ENV['DB_HOST'];
$user     = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$dbname   = $_ENV['DB_NAME'];

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $password, $options);

} catch (\PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Devolver la conexión
return $pdo;

?>