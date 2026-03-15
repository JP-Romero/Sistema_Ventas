<form action="guardar_producto.php" method="POST" enctype="multipart/form-data">
    <label for="nombre">Nombre del Producto:</label>
    <input type="text" id="nombre" name="nombre" required><br><br>

    <label for="precio">Precio:</label>
    <input type="number" id="precio" name="precio" step="0.01" required><br><br>

    <label for="imagen">Imagen del Producto:</label>
    <input type="file" id="imagen" name="imagen" accept="image/*"><br><br>

    <input type="submit" value="Guardar Producto">
</form>