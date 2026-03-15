<?php
// 1. Conexión a la base de datos (asumiendo que ya tienes una conexión)
$servername = "localhost";
$username = "root"; // Tu usuario de XAMPP
$password = "";     // Tu contraseña de XAMPP (vacía por defecto)
$dbname = "sistema_ventas"; // Reemplaza con el nombre de tu base de datos

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// 2. Procesar los datos del formulario
$nombre = $_POST['nombre'];
$precio = $_POST['precio'];
$imagen_url = NULL; // Por defecto, no hay imagen

// 3. Manejo de la subida de la imagen
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
    $target_dir = "img/productos/"; // Carpeta donde se guardarán las imágenes
    $file_name = basename($_FILES["imagen"]["name"]); // Obtiene el nombre original del archivo
    $target_file = $target_dir . uniqid() . "_" . $file_name; // Genera un nombre único para evitar colisiones
    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

    // Validar tipo de archivo (opcional pero recomendado)
    $check = getimagesize($_FILES["imagen"]["tmp_name"]);
    if($check !== false) {
        // Permitir ciertos formatos de archivo
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
        && $imageFileType != "gif" ) {
            echo "Lo siento, solo se permiten archivos JPG, JPEG, PNG y GIF.";
            exit();
        }

        // Mover el archivo subido a la carpeta de destino
        if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $target_file)) {
            $imagen_url = $target_file; // Guardar la ruta relativa en la base de datos
            echo "La imagen ". htmlspecialchars( $file_name). " ha sido subida.<br>";
        } else {
            echo "Lo siento, hubo un error al subir tu archivo.<br>";
        }
    } else {
        echo "El archivo no es una imagen válida.<br>";
    }
}

// 4. Insertar datos en la base de datos
$sql = "INSERT INTO productos (nombre, precio, imagen_url) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sds", $nombre, $precio, $imagen_url); // s: string, d: double, s: string

if ($stmt->execute()) {
    echo "Nuevo producto añadido exitosamente.";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$stmt->close();
$conn->close();
?>
