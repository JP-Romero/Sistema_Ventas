<?php
session_start();
include("config/conexion.php");
include_once("log_function.php"); // Incluir la función de registro

// Debe ser la primera línea del archivo PHP, antes de cualquier output
header('Content-Type: text/html; charset=UTF-8');

// Configurar la conexión a la base de datos con UTF-8
$conexion->exec("SET NAMES 'utf8mb4'");
$conexion->exec("SET CHARACTER SET utf8mb4");
$conexion->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['username'] ?? '');
    $clave = $_POST['password'] ?? '';

    if (empty($usuario) || empty($clave)) {
        $error = "Debe ingresar usuario y contraseña.";
    } else {
        try {
            $stmt = $conexion->prepare("SELECT id, usuario, password, rol FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $datos = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($datos && password_verify($clave, $datos['password'])) {
                session_regenerate_id(true);
                $_SESSION['usuario'] = $datos['usuario'];
                $_SESSION['rol'] = $datos['rol'];
                $_SESSION['user_id'] = $datos['id'];
                $_SESSION['login_time'] = time();

                // Registrar la actividad de login
                registrar_actividad($conexion, $datos['id'], 'login', 'El usuario inició sesión exitosamente.');

                // === Lógica de Turno ===
                if ($datos['rol'] === 'cajero' || $datos['rol'] === 'admin') {
                    $stmt_check = $conexion->prepare("SELECT id FROM caja_sesiones WHERE usuario_nombre = ? AND estado = 'abierto'");
                    $stmt_check->execute([$datos['usuario']]);
                    $turno_abierto = $stmt_check->fetch();

                    if (!$turno_abierto) {
                        header("Location: iniciar_turno.php");
                        exit;
                    }
                }
                
                header("Location: panel.php");
                exit;
            } else {
                // Registrar intento de login fallido
                $stmt_user_id = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                $stmt_user_id->execute([$usuario]);
                $user_id_failed = $stmt_user_id->fetchColumn();
                if ($user_id_failed) {
                    registrar_actividad($conexion, $user_id_failed, 'login_fallido', 'Intento de inicio de sesión con clave incorrecta.');
                }
                $error = "Usuario o clave incorrecta";
            }
        } catch (PDOException $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Login - Sistema de Ventas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            --card-bg: #ffffff;
            --text-primary: #333;
            --text-secondary: #555;
            --input-bg: #ffffff;
            --input-border: #ddd;
            --input-focus: #2575fc;
            --btn-bg: #2575fc;
            --btn-hover: #1a5bc5;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            --border-color: #e9ecef;
            --saludo-color: #2575fc;
            --font: 'Poppins', sans-serif;
        }
        [data-theme="dark"] {
            --bg-gradient: linear-gradient(135deg, #1e2024 0%, #232526 100%);
            --card-bg: #1a1a1a;
            --text-primary: #e0e0e0;
            --text-secondary: #aaaaaa;
            --input-bg: #2d2d2d;
            --input-border: #555;
            --input-focus: #4a90e2;
            --btn-bg: #4a90e2;
            --btn-hover: #3a7bc8;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            --border-color: #333;
            --saludo-color: #4a90e2;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: var(--font);
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            transition: background 0.5s ease;
            padding: 20px;
        }
        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 20px;
            cursor: pointer;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
            z-index: 10;
        }
        .theme-toggle:hover {
            transform: rotate(30deg);
            background: rgba(255, 255, 255, 0.3);
        }
        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }
        .login-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
        }
        .logo-login {
            text-align: center;
            padding: 30px 20px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
        }
        .logo-login img {
            max-width: 100px;
            height: auto;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .login-saludo {
            margin-top: 15px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .login-saludo h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--saludo-color);
        }
        .login-saludo p {
            margin: 3px 0;
            font-size: 13px;
            color: var(--text-secondary);
        }
        form {
            padding: 30px;
        }
        label {
            display: block;
            margin: 14px 0 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 15px;
            color: var(--text-primary);
            transition: all 0.3s ease;
            outline: none;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(37, 117, 252, 0.2);
        }
        .password-container {
            position: relative;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 38px;
            cursor: pointer;
            font-size: 14px;
            color: var(--text-secondary);
            user-select: none;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: var(--input-focus);
        }
        input[type="submit"] {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            background: var(--btn-bg);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        input[type="submit"]:hover {
            background: var(--btn-hover);
        }
        .forgot-password {
            text-align: right;
            margin-top: 8px;
            font-size: 14px;
        }
        .forgot-password a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
        }
        .forgot-password a:hover {
            text-decoration: underline;
            color: var(--input-focus);
        }
        @media (max-width: 480px) {
            .theme-toggle {
                width: 44px;
                height: 44px;
                font-size: 18px;
                top: 15px;
                right: 15px;
            }
            .login-wrapper {
                padding: 10px;
            }
            .login-card {
                border-radius: 12px;
            }
            form, .logo-login {
                padding: 20px;
            }
            .login-saludo h3 {
                font-size: 16px;
            }
            .login-saludo p {
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="login-page">
    <!-- Botón para cambiar tema -->
    <button class="theme-toggle" id="themeToggle">🌓</button>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="logo-login">
                <img src="img/productos/logo.png" alt="Logo del sistema">
                <div class="login-saludo">
                    <h3 id="saludo"></h3>
                    <p id="fecha-actual"></p>
                    <p id="hora-actual"></p>
                </div>
            </div>
            <form id="loginForm" action="login.php" method="POST" autocomplete="on">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" autocomplete="username" placeholder="Ingresa tu usuario" required autofocus>
                <label for="password">Contraseña</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                    <span class="toggle-password" onclick="togglePassword()">👁️</span>
                </div>
                <div class="forgot-password">
                    <a href="recuperar_clave.php">¿Olvidaste tu contraseña?</a>
                </div>
                <input type="submit" value="🔐 Iniciar Sesión">
            </form>
        </div>
    </div>
    <script>
        // Toggle de tema claro/oscuro
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            body.setAttribute('data-theme', 'dark');
            themeToggle.textContent = '☀️';
        }
        themeToggle.addEventListener('click', () => {
            const currentTheme = body.getAttribute('data-theme');
            if (currentTheme === 'dark') {
                body.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                themeToggle.textContent = '🌑';
            } else {
                body.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeToggle.textContent = '☀️';
            }
        });

        // Toggle de visibilidad de contraseña
        function togglePassword() {
            const passwordField = document.getElementById("password");
            const toggle = document.querySelector(".toggle-password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                toggle.textContent = "🙈";
            } else {
                passwordField.type = "password";
                toggle.textContent = "👁️";
            }
        }

        // Saludo y reloj dinámico
        document.addEventListener('DOMContentLoaded', function () {
            function actualizarSaludoYFechaHora() {
                const saludo = document.getElementById('saludo');
                const fecha = document.getElementById('fecha-actual');
                const hora = document.getElementById('hora-actual');
                const ahora = new Date();
                const opcionesFecha = {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    timeZone: 'America/Managua'
                };
                const opcionesHora = {
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true,
                    timeZone: 'America/Managua'
                };
                const fechaFormateada = ahora.toLocaleDateString('es-NI', opcionesFecha);
                const horaFormateada = ahora.toLocaleTimeString('es-NI', opcionesHora);
                let textoSaludo = "👋 ¡Bienvenido!";
                const h = ahora.getHours();
                if (h >= 6 && h < 12) textoSaludo = "🌅 Buenos días";
                else if (h >= 12 && h < 18) textoSaludo = "☀️ Buenas tardes";
                else textoSaludo = "🌙 Buenas noches";
                if (saludo) saludo.textContent = textoSaludo;
                if (fecha) fecha.textContent = fechaFormateada;
                if (hora) hora.textContent = horaFormateada;
            }
            actualizarSaludoYFechaHora();
            setInterval(actualizarSaludoYFechaHora, 1000);
        });
    </script>
    <?php if (!empty($error)): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Acceso denegado',
                text: <?php echo json_encode($error); ?>,
                confirmButtonText: 'Intentar de nuevo',
                timer: 3500,
                timerProgressBar: true
            }).then(() => {
                const username = document.getElementById("username");
                const password = document.getElementById("password");
                if (username && password) {
                    password.value = "";
                    username.focus();
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
