<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Logo</title>
    <style>
        /* Estilos generales del cuerpo */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            color: #333;
            display: flex;
            flex-direction: column; /* Organiza los elementos verticalmente */
            justify-content: center; /* Centra verticalmente */
            align-items: center; /* Centra horizontalmente */
            min-height: 100vh; /* Ocupa al menos el 100% de la altura de la ventana */
        }

        /* Contenedor principal para el contenido */
        .logo-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px; /* Ancho máximo para el contenedor */
            width: 90%; /* Ancho responsivo */
        }

        /* Estilos para el encabezado */
        h2 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 25px;
        }

        /* Estilos para la imagen del logo */
        .logo-img {
            max-width: 200px; /* Asegura que la imagen no exceda su tamaño original ni el contenedor */
            height: auto; /* Mantiene la proporción de la imagen */
            border-radius: 8px; /* Bordes ligeramente redondeados para la imagen */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); /* Sombra suave para la imagen */
        }

        /* Mensaje de fallback para la imagen */
        .image-fallback {
            color: #e74c3c;
            font-size: 0.9em;
            margin-top: 10px;
            display: block; /* Asegura que el mensaje esté en su propia línea */
        }

        /* Estilos responsivos */
        @media (max-width: 600px) {
            .logo-container {
                padding: 20px;
            }
            h2 {
                font-size: 1.8em;
            }
            .logo-img {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <h2>Este es tu logo:</h2>
        <!--
            Asegúrate de que la ruta 'img/productos/logo.png' sea correcta y accesible desde tu servidor web.
            Si la imagen no carga, puedes usar un atributo 'onerror' para mostrar un mensaje o una imagen de placeholder.
        -->
        <img src="img/productos/logo.png" alt="Logo de la empresa" class="logo-img"
             onerror="this.onerror=null; this.src='https://placehold.co/200x200/cccccc/333333?text=Logo+No+Encontrado'; this.nextElementSibling.style.display='block';">
        <span class="image-fallback" style="display: none;">No se pudo cargar el logo. Verifica la ruta.</span>
    </div>
</body>
</html>
