<?php
// =============================================================================
// SCRIPT PARA LIMPIAR LA TABLA DE VENTAS (`tickets`) Y SUS DETALLES (v2)
// =============================================================================
//
// **ADVERTENCIA:** Este script eliminará PERMANENTEMENTE todos los datos de las
// tablas de ventas. Esta acción borrará todo el historial de ventas.
//
// **Instrucciones de uso:**
// 1. Coloque este archivo en la raíz de su proyecto.
// 2. Ejecútelo desde la línea de comandos con: `php limpiar_ventas.php`
// 3. Elimine este archivo después de usarlo para evitar ejecuciones accidentales.
//
// =============================================================================

echo "========================================================\n";
echo "== Script para Limpiar las Tablas de Ventas y Detalles ==\n";
echo "========================================================\n\n";

function connect_db() {
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->load();
        }
        $host     = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $user     = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        $dbname   = $_ENV['DB_NAME'] ?? 'sistema_ventas';
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, $user, $password, $options);
    } catch (Exception $e) {
        echo "ERROR CRÍTICO: No se pudo conectar a la base de datos: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function table_exists($pdo, $table_name) {
    try {
        $result = $pdo->query("SELECT 1 FROM $table_name LIMIT 1");
    } catch (Exception $e) {
        return false;
    }
    return $result !== false;
}

$pdo = connect_db();
$tables_to_truncate = [
    'detalle_ticket',       // Hija
    'inventario_venta',     // Hija
    'tickets'               // Padre
];

try {
    echo "Iniciando proceso de limpieza...\n";
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
    echo "[INFO] Revisión de claves foráneas desactivada.\n";

    foreach ($tables_to_truncate as $table) {
        if (table_exists($pdo, $table)) {
            $pdo->exec("TRUNCATE TABLE `$table`;");
            echo "[ÉXITO] Tabla '$table' limpiada.\n";
        } else {
            echo "[OMITIDO] Tabla '$table' no encontrada.\n";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    echo "[INFO] Revisión de claves foráneas reactivada.\n";

    echo "\n---------------------------------------------------\n";
    echo "✅ ¡ÉXITO! El proceso de limpieza de ventas ha finalizado.\n";
    echo "---------------------------------------------------\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: Se ha producido un error al intentar limpiar las tablas.\n";
    echo "Detalle del error: " . $e->getMessage() . "\n";
    // Asegurarse de reactivar las claves foráneas en caso de error.
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
    exit(1);
}

?>
