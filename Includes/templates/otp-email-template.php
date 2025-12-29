<?php
/**
 * Template para email OTP
 * 
 * Variáveis disponíveis:
 * @var string $otp_code - Código OTP de 6 dígitos
 * @var string $site_name - Nome do site
 * @var string $site_url - URL do site
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seu código de acesso</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <!-- Header -->
        <div style="background-color: #ffffff; padding: 40px 30px 20px; text-align: center; border-bottom: 1px solid #e5e5e5;">
            <h1 style="margin: 0; font-size: 24px; font-weight: 600; color: #2c3e50; letter-spacing: -0.5px;">
                <?php echo esc_html($site_name); ?>
            </h1>
        </div>

        <!-- Main Content -->
        <div style="padding: 40px 30px;">
            <!-- Greeting -->
            <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 500; color: #2c3e50; line-height: 1.3;">
                Seu código de acesso
            </h2>
            
            <p style="margin: 0 0 30px; font-size: 16px; line-height: 1.6; color: #555555;">
                Use o código abaixo para acessar sua conta:
            </p>

            <!-- OTP Code Box -->
            <div style="text-align: center; margin: 30px 0 40px;">
                <div style="display: inline-block; background-color: #f8f9fa; border: 2px solid #e9ecef; border-radius: 8px; padding: 20px 30px;">
                    <div style="font-size: 32px; font-weight: 700; color: #2c3e50; letter-spacing: 4px; font-family: 'Courier New', monospace;">
                        <?php echo esc_html($otp_code); ?>
                    </div>
                </div>
            </div>

            <!-- Instructions -->
            <div style="background-color: #f8f9fa; border-left: 4px solid #dee2e6; padding: 20px; margin: 30px 0; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; font-size: 14px; color: #6c757d; line-height: 1.5;">
                    <strong style="color: #495057;">Instruções:</strong><br>
                    • Este código expira em <strong>5 minutos</strong><br>
                    • Digite-o na tela de login para acessar sua conta<br>
                    • Não compartilhe este código com ninguém
                </p>
            </div>

            <!-- Security Note -->
            <p style="margin: 30px 0 0; font-size: 14px; line-height: 1.5; color: #6c757d;">
                Se você não solicitou este código, pode ignorar este email com segurança. 
                Sua conta permanecerá protegida.
            </p>
        </div>

        <!-- Footer -->
        <div style="background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e5e5e5;">
            <p style="margin: 0 0 10px; font-size: 14px; color: #6c757d;">
                Esta é uma mensagem automática de segurança
            </p>
            <p style="margin: 0; font-size: 12px; color: #adb5bd;">
                <a href="<?php echo esc_url($site_url); ?>" style="color: #6c757d; text-decoration: none;">
                    <?php echo esc_html($site_name); ?>
                </a>
            </p>
        </div>
    </div>

    <!-- Spacer for email clients -->
    <div style="height: 40px;"></div>
</body>
</html>
