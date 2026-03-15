<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$mail = new PHPMailer(true);
$exito = false;
$errorMensaje = '';

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mcalebr04@gmail.com';             // ✅ Tu correo real
    $mail->Password   = 'vtxj rkap zvmo ekuz';     // ✅ Reemplaza con la contraseña de aplicación desde Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('mcalebr04@gmail.com', 'Sistema de Prueba');
    $mail->addAddress('mcalebr04@gmail.com', 'Prueba'); //✅ Reemplaza con el correo receptor

    $mail->isHTML(true);
    $mail->Subject = 'Prueba de envio SMTP';
    $mail->Body    = '<h3>¡Todo funciona correctamente! ✅</h3><p>Este correo confirma que la conexión SMTP está activa.</p>';
    $mail->AltBody = 'Este mensaje confirma que SMTP funciona correctamente.';

    $mail->send();
    $exito = true;
} catch (Exception $e) {
    $errorMensaje = $mail->ErrorInfo;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prueba SMTP</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
<?php if ($exito): ?>
Swal.fire({
    title: '✅ ¡Correo Enviado!',
    text: 'La prueba SMTP fue exitosa.',
    icon: 'success',
    confirmButtonText: 'Genial'
});
<?php else: ?>
Swal.fire({
    title: '❌ Error de envío',
    text: <?= json_encode($errorMensaje) ?>,
    icon: 'error',
    confirmButtonText: 'Revisar'
});
<?php endif; ?>
</script>
</body>
</html>
