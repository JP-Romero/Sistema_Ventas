<?php
session_start();
include("config/conexion.php");

// Obtener categorías para el filtro de productos
$categorias = $conexion->query("SELECT DISTINCT categoria FROM productos")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Exportar Reportes</title>
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f4f6f8;
      padding: 40px;
      color: #333;
    }

    .contenedor {
      max-width: 600px;
      margin: auto;
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 0 16px rgba(0,0,0,0.1);
    }

    h2 {
      text-align: center;
      margin-bottom: 30px;
    }

    form {
      margin-bottom: 30px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
    }

    input, select {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 6px;
      border: 1px solid #ccc;
    }

    button {
      background-color: #3498db;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      width: 100%;
    }

    button:hover {
      background-color: #2980b9;
    }

    .separador {
      border-top: 1px solid #ddd;
      margin: 30px 0;
    }
  </style>
</head>
<body>
  <div class="contenedor">
    <h2>📤 Exportar Reportes</h2>

    <!-- Exportar productos -->
    <form action="exportar_excel_productos.php" method="GET">
      <label for="categoria">Filtrar por categoría:</label>
      <select name="categoria" id="categoria">
        <option value="">Todas</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= htmlspecialchars($c['categoria']) ?>"><?= htmlspecialchars($c['categoria']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">📦 Exportar Productos</button>
    </form>

    <div class="separador"></div>

    <!-- Exportar ventas -->
    <form action="exportar_excel_ventas.php" method="GET">
      <label for="desde">Desde:</label>
      <input type="date" name="desde" id="desde" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">

      <label for="hasta">Hasta:</label>
      <input type="date" name="hasta" id="hasta" value="<?= date('Y-m-d') ?>">

      <button type="submit">📈 Exportar Ventas</button>
    </form>
  </div>
</body>
</html>
