<?php
// Configuración y funciones para envío de correos

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once BASE_PATH . 'vendor/autoload.php';

function enviarCorreo($destinatario, $asunto, $cuerpo, $esHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($destinatario);
        
        // Contenido
        $mail->isHTML($esHTML);
        $mail->Subject = $asunto;
        $mail->Body = $cuerpo;
        
        if (!$esHTML) {
            $mail->AltBody = strip_tags($cuerpo);
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar correo: " . $mail->ErrorInfo);
        return false;
    }
}

function enviarCorreoPrimeraVez($email, $nombre, $token) {
    $url = BASE_URL . 'cambiar_password.php?token=' . $token . '&primera_vez=1';
    
    $asunto = 'Bienvenido a TEC-UCT Autoevaluación - Cambio de Contraseña';
    
    $cuerpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .button { display: inline-block; padding: 12px 24px; background-color: #0066CC; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>TEC-UCT Autoevaluación</h2>
            </div>
            <div class='content'>
                <p>Estimado/a <strong>$nombre</strong>,</p>
                <p>Bienvenido/a a la plataforma de autoevaluación del Instituto Tecnológico TEC-UCT.</p>
                <p>Para completar su registro, debe cambiar su contraseña por defecto. Haga clic en el siguiente enlace:</p>
                <p style='text-align: center;'>
                    <a href='$url' class='button'>Cambiar Contraseña</a>
                </p>
                <p>O copie y pegue este enlace en su navegador:</p>
                <p style='word-break: break-all; color: #0066CC;'>$url</p>
                <p><strong>Este enlace expirará en 1 hora.</strong></p>
                <p>Si no solicitó este cambio, por favor ignore este correo.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " TEC-UCT - Universidad Católica de Temuco</p>
                <p>Este es un correo automático, por favor no responda.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviarCorreo($email, $asunto, $cuerpo);
}

function enviarCorreoRecuperacion($email, $nombre, $token) {
    $url = BASE_URL . 'cambiar_password.php?token=' . $token;
    
    $asunto = 'Recuperación de Contraseña - TEC-UCT Autoevaluación';
    
    $cuerpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #003366; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .button { display: inline-block; padding: 12px 24px; background-color: #0066CC; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>TEC-UCT Autoevaluación</h2>
            </div>
            <div class='content'>
                <p>Estimado/a <strong>$nombre</strong>,</p>
                <p>Hemos recibido una solicitud para recuperar su contraseña en la plataforma de autoevaluación TEC-UCT.</p>
                <p>Haga clic en el siguiente enlace para restablecer su contraseña:</p>
                <p style='text-align: center;'>
                    <a href='$url' class='button'>Restablecer Contraseña</a>
                </p>
                <p>O copie y pegue este enlace en su navegador:</p>
                <p style='word-break: break-all; color: #0066CC;'>$url</p>
                <p><strong>Este enlace expirará en 1 hora.</strong></p>
                <p>Si no solicitó este cambio, por favor ignore este correo y su contraseña permanecerá sin cambios.</p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " TEC-UCT - Universidad Católica de Temuco</p>
                <p>Este es un correo automático, por favor no responda.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return enviarCorreo($email, $asunto, $cuerpo);
}
?>

