<?php
namespace LknWc\WcInvoicePayment\Includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe responsável por gerenciar autenticação OTP via email
 */
final class WcPaymentInvoiceOtpEmail {
    
    private $loader;

    public function __construct($loader) {
        $this->loader = $loader;
    }

    /**
     * Registra endpoints REST API para OTP
     */
    public function registerEndpoints() {
        register_rest_route('invoice_payments', '/send_otp_code', array(
            'methods' => 'POST',
            'callback' => array($this, 'sendOtpCode'),
            'permission_callback' => array($this, 'checkOtpPermissions'),
            'args' => array(
                'email' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_email($param);
                    }
                )
            )
        ));

        register_rest_route('invoice_payments', '/verify_otp_code', array(
            'methods' => 'POST',
            'callback' => array($this, 'verifyOtpCode'),
            'permission_callback' => array($this, 'checkOtpPermissions'),
            'args' => array(
                'email' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_email($param);
                    }
                ),
                'code' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{6}$/', $param);
                    }
                )
            )
        ));
    }

    /**
     * Verifica permissões para endpoints OTP
     */
    public function checkOtpPermissions($request) {
        // Verificar se OTP está ativado
        if (!$this->is_otp_enabled()) {
            return new \WP_Error('otp_disabled', 'OTP não está ativado', array('status' => 403));
        }

        return true; // Simplificar temporariamente para teste
    }

    /**
     * Gera um código OTP de 6 dígitos
     */
    private function generate_otp_code() {
        return sprintf('%06d', mt_rand(0, 999999));
    }

    /**
     * Obtém o tempo de expiração configurado em minutos
     */
    private function get_otp_expiration_minutes() {
        $expiration_minutes = get_option('lkn_wcip_otp_email_expiration_time', 5);
        
        // Validação: mínimo 1 minuto, máximo 60 minutos
        $expiration_minutes = max(1, min(60, (int) $expiration_minutes));
        
        return $expiration_minutes;
    }

    /**
     * Salva código OTP nas options do WordPress
     */
    private function save_otp_code($email, $code) {
        $expiration_minutes = $this->get_otp_expiration_minutes();
        
        $otp_data = array(
            'code' => $code,
            'email' => $email,
            'expires_at' => time() + ($expiration_minutes * 60),
            'used' => false,
            'created_at' => time()
        );

        // Usar hash do email como chave para evitar conflitos
        $email_hash = md5($email);
        update_option('lkn_wcip_otp_' . $email_hash, $otp_data);
        
        return true;
    }

    /**
     * Recupera código OTP válido para um email
     */
    private function get_valid_otp_code($email) {
        $email_hash = md5($email);
        $otp_data = get_option('lkn_wcip_otp_' . $email_hash);

        if (!$otp_data || !is_array($otp_data)) {
            return false;
        }

        // Verifica se não foi usado e não expirou
        if ($otp_data['used'] || $otp_data['expires_at'] < time()) {
            // Remove código expirado/usado
            delete_option('lkn_wcip_otp_' . $email_hash);
            return false;
        }

        return $otp_data;
    }

    /**
     * Marca código como usado
     */
    private function mark_code_as_used($email) {
        $email_hash = md5($email);
        $otp_data = get_option('lkn_wcip_otp_' . $email_hash);
        
        if ($otp_data && is_array($otp_data)) {
            $otp_data['used'] = true;
            update_option('lkn_wcip_otp_' . $email_hash, $otp_data);
        }
    }

    /**
     * Remove códigos expirados (cleanup)
     */
    private function cleanup_expired_codes() {
        global $wpdb;
        
        // Busca options com o padrão do OTP
        $results = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
            WHERE option_name LIKE 'lkn_wcip_otp_%'"
        );

        foreach ($results as $row) {
            $otp_data = get_option($row->option_name);
            if ($otp_data && is_array($otp_data)) {
                // Remove se expirado ou usado
                if ($otp_data['used'] || $otp_data['expires_at'] < time()) {
                    delete_option($row->option_name);
                }
            }
        }
    }

    /**
     * Envia email com código OTP
     */
    private function send_otp_email($email, $code) {
        $subject = sprintf(
            __('Seu código de acesso: %s', 'wc-invoice-payment'),
            $code
        );

        // Dados para o template
        $template_data = array(
            'otp_code' => $code,
            'site_name' => \get_bloginfo('name'),
            'site_url' => \home_url(),
            'expiration_minutes' => $this->get_otp_expiration_minutes()
        );

        // Gera o HTML do email usando template
        $message = $this->render_otp_email_template($template_data);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . \get_bloginfo('name') . ' <' . \get_option('admin_email') . '>'
        );
        
        return \wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Renderiza template do email OTP
     */
    private function render_otp_email_template($data) {
        // Extrai variáveis para o template
        extract($data);
        
        // Captura output do template
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/otp-email-template.php';
        return ob_get_clean();
    }

    /**
     * Endpoint: Envia código OTP
     */
    public function sendOtpCode($request) {
        $parameters = $request->get_params();
        $email = sanitize_email($parameters['email']);

        // Limpa códigos expirados
        $this->cleanup_expired_codes();

        // Verifica se usuário existe (modo apenas login)
        if ($this->is_login_only_mode() && !email_exists($email)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Email não cadastrado.', 'wc-invoice-payment')
            ), 400);
        }

        // Verifica se já existe código válido
        $existing_code = $this->get_valid_otp_code($email);
        
        if ($existing_code) {
            $code = $existing_code['code'];
        } else {
            // Gera novo código
            $code = $this->generate_otp_code();
            
            // Salva novo código
            $this->save_otp_code($email, $code);
        }
        
        // Envia email
        if ($this->send_otp_email($email, $code)) {
            return new \WP_REST_Response(array(
                'success' => true,
                'message' => __('Código enviado para seu email.', 'wc-invoice-payment')
            ), 200);
        } else {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Erro ao enviar código.', 'wc-invoice-payment')
            ), 500);
        }
    }

    /**
     * Endpoint: Verifica código OTP
     */
    public function verifyOtpCode($request) {
        $parameters = $request->get_params();
        $email = sanitize_email($parameters['email']);
        $code = sanitize_text_field($parameters['code']);

        $result = $this->process_otp_login($email, $code);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_REST_Response($result, 400);
        }
    }

    /**
     * Valida código OTP
     */
    public function verify_otp_code($email, $code) {
        $otp_data = $this->get_valid_otp_code($email);
        
        if (!$otp_data) {
            return false;
        }

        if ($otp_data['code'] === $code && $otp_data['email'] === $email) {
            // Marca código como usado
            $this->mark_code_as_used($email);
            return true;
        }
        
        return false;
    }

    /**
     * Obtém configuração OTP
     */
    public function get_otp_mode() {
        return get_option('lkn_wcip_otp_email_enable_type', 'disabled');
    }

    /**
     * Verifica se OTP está ativo
     */
    public function is_otp_enabled() {
        return $this->get_otp_mode() !== 'disabled';
    }

    /**
     * Verifica se é modo completo (registro + login)
     */
    public function is_full_mode() {
        return $this->get_otp_mode() === 'register_and_login';
    }

    /**
     * Verifica se é modo apenas login
     */
    public function is_login_only_mode() {
        return $this->get_otp_mode() === 'login_only';
    }

    /**
     * Registra usuário sem senha (apenas para modo completo)
     */
    public function register_user_without_password($email) {
        if (!$this->is_full_mode()) {
            return false;
        }

        // Verifica se usuário já existe
        if (email_exists($email)) {
            return get_user_by('email', $email);
        }

        // Gera username único baseado no email
        $username = $this->generate_unique_username($email);
        
        // Gera senha aleatória (não será usada)
        $random_password = wp_generate_password(20);
        
        // Cria usuário
        $user_id = wp_create_user($username, $random_password, $email);
        
        if (is_wp_error($user_id)) {
            return false;
        }

        // Define role como customer
        $user = new \WP_User($user_id);
        $user->set_role('customer');

        // Envia emails de boas-vindas
        if (class_exists('WooCommerce')) {
            $this->send_woocommerce_welcome_email($user_id);
        }

        return $user;
    }

    /**
     * Envia email de boas-vindas do WooCommerce
     */
    private function send_woocommerce_welcome_email($user_id) {
        // Simula nova conta criada no checkout para triggerar email do WooCommerce
        if (function_exists('WC') && \WC()->mailer()) {
            $emails = \WC()->mailer()->get_emails();
            if (isset($emails['WC_Email_Customer_New_Account'])) {
                $email = $emails['WC_Email_Customer_New_Account'];
                if ($email->is_enabled()) {
                    $user = \get_userdata($user_id);
                    $key = \get_password_reset_key($user);
                    if (!\is_wp_error($key)) {
                        $email->trigger($user_id, $key);
                    }
                }
            }
        }
        
        return true;
    }

    /**
     * Gera username único baseado no email
     */
    private function generate_unique_username($email) {
        $base_username = \sanitize_user(substr($email, 0, strpos($email, '@')));
        $username = $base_username;
        $counter = 1;

        while (\username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Faz login automático do usuário
     */
    public function auto_login_user($user) {
        if (!$user || \is_wp_error($user)) {
            return false;
        }

        \wp_clear_auth_cookie();
        \wp_set_current_user($user->ID);
        \wp_set_auth_cookie($user->ID, true);

        return true;
    }

    /**
     * Processa login/registro OTP
     */
    public function process_otp_login($email, $code) {
        // Verifica código
        if (!$this->verify_otp_code($email, $code)) {
            return array(
                'success' => false,
                'message' => \__('Código inválido ou expirado.', 'wc-invoice-payment')
            );
        }

        // Verifica se usuário existe
        $user = \get_user_by('email', $email);

        if (!$user) {
            if ($this->is_full_mode()) {
                // Modo completo: cria usuário
                $user = $this->register_user_without_password($email);
                if (!$user) {
                    return array(
                        'success' => false,
                        'message' => \__('Erro ao criar conta.', 'wc-invoice-payment')
                    );
                }
            } else {
                // Modo apenas login: usuário deve existir
                return array(
                    'success' => false,
                    'message' => \__('Usuário não encontrado.', 'wc-invoice-payment')
                );
            }
        }

        // Faz login automático
        if ($this->auto_login_user($user)) {
            return array(
                'success' => true,
                'message' => \__('Login realizado com sucesso!', 'wc-invoice-payment'),
                'redirect' => \wc_get_account_endpoint_url('dashboard')
            );
        }

        return array(
            'success' => false,
            'message' => \__('Erro ao fazer login.', 'wc-invoice-payment')
        );
    }

    /**
     * Substitui formulários de login e registro
     */
    public function replace_login_register_forms() {
        // Adiciona formulário OTP
        echo $this->get_otp_form_html();
        
    }

    /**
     * Substitui apenas formulário de login
     */
    public function replace_login_form() {
        echo $this->get_otp_form_html();
    }

    /**
     * HTML do formulário OTP
     */
    private function get_otp_form_html() {
        ob_start();
        ?>
        <div class="lkn-otp-auth-wrapper">
            <div class="lkn-otp-form-container">
                <h2><?php _e('Acesse sua conta', 'wc-invoice-payment'); ?></h2>
                <p><?php _e('Digite seu email para receber um código de acesso', 'wc-invoice-payment'); ?></p>
                
                <!-- Formulário de email -->
                <form id="lkn-otp-email-form" class="lkn-otp-form" style="display: block;">
                    <div class="form-row">
                        <label for="lkn_otp_email"><?php _e('Seu email', 'wc-invoice-payment'); ?> <span class="required">*</span></label>
                        <input type="email" id="lkn_otp_email" name="email" class="input-text" required />
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="wp-element-button" name="otp_send_code" id="lkn-wc-otp-send-code">
                            <?php _e('Enviar código', 'wc-invoice-payment'); ?>
                        </button>
                    </div>
                </form>

                <!-- Formulário de código -->
                <form id="lkn-otp-code-form" class="lkn-otp-form" style="display: none;">
                    <div class="form-row">
                        <label for="lkn_otp_code"><?php _e('Código de acesso', 'wc-invoice-payment'); ?> <span class="required">*</span></label>
                        <input type="text" inputmode="numeric" id="lkn_otp_code" name="code" class="input-text" maxlength="6" pattern="[0-9]{6}" required />
                        <small style="text-align: center;">
                            <?php _e('Digite o código de 6 dígitos enviado para seu email', 'wc-invoice-payment'); ?><br>
                            <a href="#" id="lkn-otp-back"><?php _e('Voltar e cancelar.', 'wc-invoice-payment'); ?></a>
                        </small>
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="wp-element-button" name="otp_verify_code">
                            <?php _e('Verificar código', 'wc-invoice-payment'); ?>
                        </button>
                    </div>
                </form>

                <div id="lkn-otp-messages"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Adiciona scripts e estilos CSS/JavaScript
     */
    public function add_otp_scripts() {
        if (!\is_account_page()) {
            return;
        }

        // Determina se deve usar versões minificadas
        $min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        
        // Enfileira CSS
        \wp_enqueue_style(
            'wc-invoice-payment-otp',
            \plugin_dir_url(__DIR__) . "Public/css/wc-invoice-payment-otp.css",
            array(),
            '1.0.0'
        );

        // Enfileira JavaScript
        \wp_enqueue_script(
            'wc-invoice-payment-otp',
            \plugin_dir_url(__DIR__) . 'Public/js/wc-invoice-payment-otp.js',
            array('jquery', 'wp-api-fetch'),
            '1.0.0',
            true
        );

        // Localiza dados para o JavaScript
        \wp_localize_script('wc-invoice-payment-otp', 'wcInvoicePaymentOtp', array(
            'dashboardUrl' => \wc_get_account_endpoint_url('dashboard'),
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('wc_invoice_payment_otp_nonce'),
            'get_otp_mode' => $this->get_otp_mode(),
            'expiration_minutes' => $this->get_otp_expiration_minutes()
        ));
    }

    /**
     * Bloqueia registros padrão do WooCommerce quando OTP estiver ativado no modo register_and_login
     */
    public function block_woocommerce_registration($validation_error, $username, $password, $email) {
        // Só bloqueia se estiver no modo register_and_login
        if ($this->is_full_mode()) {
            $validation_error->add('registration_blocked', 
                \__('Registros tradicionais estão desabilitados.', 'wc-invoice-payment')
            );
        }
        
        return $validation_error;
    }
}
