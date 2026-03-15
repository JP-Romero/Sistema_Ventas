<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

include("config/conexion.php");
$usuario_nombre = $_SESSION['usuario'];
$mensaje = '';

// Verificar si el usuario ya tiene un turno abierto
$stmt_check = $conexion->prepare("SELECT id FROM caja_sesiones WHERE usuario_nombre = ? AND estado = 'abierto'");
$stmt_check->execute([$usuario_nombre]);
if ($stmt_check->fetch()) {
    header("Location: panel.php"); // Si ya tiene turno, redirigir al panel
    exit;
}

// Lista de denominaciones de moneda (NIO)
$denominaciones = [
    '1000' => 'Billetes de C$1000', '500' => 'Billetes de C$500', '200' => 'Billetes de C$200',
    '100' => 'Billetes de C$100', '50' => 'Billetes de C$50', '20' => 'Billetes de C$20',
    '10' => 'Billetes de C$10', '5' => 'Monedas de C$5', '1' => 'Monedas de C$1',
    '0.50' => 'Monedas de C$0.50', '0.25' => 'Monedas de C$0.25'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto_inicial = 0;
    $denominaciones_post = $_POST['denominaciones'];

    // Calcular el monto total inicial desde las denominaciones
    foreach ($denominaciones_post as $valor => $cantidad) {
        if (!empty($cantidad) && is_numeric($cantidad)) {
            $monto_inicial += (float)$valor * (int)$cantidad;
        }
    }

    if ($monto_inicial >= 0) {
        try {
            $conexion->beginTransaction();

            // 1. Insertar en caja_sesiones
            $stmt_sesion = $conexion->prepare(
                "INSERT INTO caja_sesiones (usuario_nombre, monto_inicial, fecha_apertura, estado) VALUES (?, ?, NOW(), 'abierto')"
            );
            $stmt_sesion->execute([$usuario_nombre, $monto_inicial]);
            $sesion_id = $conexion->lastInsertId();

            // 2. Insertar en caja_denominaciones
            $stmt_denom = $conexion->prepare(
                "INSERT INTO caja_denominaciones (sesion_id, tipo, denominacion, cantidad, total_denominacion) VALUES (?, 'apertura', ?, ?, ?)"
            );
            foreach ($denominaciones_post as $valor => $cantidad) {
                $cantidad_int = (int)$cantidad;
                if ($cantidad_int > 0) {
                    $total_denominacion = (float)$valor * $cantidad_int;
                    $stmt_denom->execute([$sesion_id, $valor, $cantidad_int, $total_denominacion]);
                }
            }

            $conexion->commit();
            header("Location: panel.php"); // Redirigir al panel después de iniciar el turno
            exit;

        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = "Error al iniciar el turno: " . $e->getMessage();
        }
    } else {
        $mensaje = "El monto inicial no puede ser negativo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Turno - Arqueo de Caja</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .container { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 600px; }
        h1 { text-align: center; color: #2575fc; margin-bottom: 10px; }
        p { text-align: center; color: #666; margin-bottom: 30px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 8px; font-weight: 500; color: #333; }
        input { width: 100%; padding: 10px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 16px; text-align: right; }
        .total-container { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: right; }
        #total-display { font-size: 28px; font-weight: 700; color: #27ae60; }
        .btn { display: block; width: 100%; padding: 15px; background: linear-gradient(135deg, #2575fc, #6a11cb); color: white; border: none; border-radius: 8px; font-size: 18px; cursor: pointer; margin-top: 30px; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(37, 117, 252, 0.3); }
        .error { color: #e74c3c; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-cash-register"></i> Iniciar Turno</h1>
        <p>Realiza el arqueo de caja para comenzar tu jornada.</p>
        <?php if ($mensaje): ?>
            <p class="error"><?php echo $mensaje; ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="form-grid">
                <?php foreach ($denominaciones as $valor => $texto): ?>
                <div class="form-group">
                    <label for="den_<?php echo str_replace('.', '_', $valor); ?>"><?php echo $texto; ?></label>
                    <input type="number" id="den_<?php echo str_replace('.', '_', $valor); ?>" name="denominaciones[<?php echo $valor; ?>]" min="0" step="1" value="0" data-valor="<?php echo $valor; ?>" oninput="calcularTotal()">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="total-container">
                <h2>Total Inicial: <span id="total-display">C$ 0.00</span></h2>
            </div>
            <button type="submit" class="btn">Comenzar Turno</button>
        </form>
    </div>

    <script>
        function calcularTotal() {
            let total = 0;
            const inputs = document.querySelectorAll('input[data-valor]');
            inputs.forEach(input => {
                const valor = parseFloat(input.dataset.valor);
                const cantidad = parseInt(input.value, 10) || 0;
                if (cantidad > 0) {
                    total += valor * cantidad;
                }
            });
            document.getElementById('total-display').textContent = 'C$ ' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    </script>
</body>
</html>
