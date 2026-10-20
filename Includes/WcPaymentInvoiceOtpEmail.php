<?php
namespace LknWc\WcInvoicePayment\Includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autenticação OTP via email.
 *
 * Proteções contra força bruta e enumeração de usuários:
 * - código guardado apenas como hash (nunca em texto plano) em transient (não autoload);
 * - limite de tentativas de verificação por email, com invalidação do código;
 * - bloqueio temporário após estourar o limite (aplicado ao envio e à verificação);
 * - throttle de reenvio e limite de envios por email/IP;
 * - limite de falhas por IP;
 * - mensagem neutra no envio quando o email não existe (modo apenas login).
 */
final class WcPaymentInvoiceOtpEmail {

    /**
     * Tentativas de verificação antes de invalidar o código.
     */
    private const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * Duração do bloqueio após estourar as tentativas (segundos).
     */
    private const LOCKOUT_SECONDS = 900;

    /**
     * Intervalo mínimo entre reenvios do mesmo email (segundos).
     */
    private const RESEND_THROTTLE_SECONDS = 60;

    /**
     * Envios permitidos por janela.
     */
    private const SEND_LIMIT_EMAIL = 10;
    private const SEND_LIMIT_IP = 20;

    /**
     * Tamanho da janela de envios (segundos).
     */
    private const SEND_WINDOW_SECONDS = 3600;

    /**
     * Falhas de verificação permitidas por IP na janela.
     */
    private const IP_FAIL_LIMIT = 30;
    private const IP_FAIL_WINDOW_SECONDS = 900;

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
                        return (bool) preg_match('/^\d{6}$/', $param);
                    }
                )
            )
        ));
    }

    /**
     * Verifica permissões para endpoints OTP.
     *
     * As rotas são públicas por natureza (formulário de login), portanto a proteção
     * contra abuso fica nos limites de tentativa aplicados em cada callback.
     */
    public function checkOtpPermissions($request) {
        if (!$this->is_otp_enabled()) {
            return new \WP_Error('otp_disabled', 'OTP não está ativado', array('status' => 403));
        }

        return true;
    }

    /**
     * Gera um código OTP de 6 dígitos
     */
    private function generate_otp_code() {
        return sprintf('%06d', wp_rand(0, 999999));
    }

    /**
     * Hash do código OTP (com o salt do site: o banco nunca guarda o código em texto plano)
     */
    private function hash_otp_code($code) {
        return hash_hmac('sha256', (string) $code, wp_salt('auth'));
    }

    /**
     * Obtém o tempo de expiração configurado em minutos
     */
    private function get_otp_expiration_minutes() {
        $expiration_minutes = get_option('lkn_wcip_otp_email_expiration_time', 10);
        
        // Validação: mínimo 1 minuto, máximo 60 minutos
        $expiration_minutes = max(1, min(60, (int) $expiration_minutes));
        
        return $expiration_minutes;
    }

    /**
     * Identificador do email usado nas chaves de option/transient
     */
    private function get_email_key($email) {
        return md5($email);
    }

    /**
     * Nome da chave onde o código OTP fica guardado
     */
    private function get_otp_option_name($email) {
        return 'lkn_wcip_otp_' . $this->get_email_key($email);
    }

    /**
     * Salva código OTP (transient: expira sozinho e não entra no autoload)
     */
    private function save_otp_code($email, $code) {
        $expiration_minutes = $this->get_otp_expiration_minutes();
        $now = time();

        $otp_data = array(
            'hash' => $this->hash_otp_code($code),
            'email' => $email,
            'expires_at' => $now + ($expiration_minutes * 60),
            'attempts' => 0,
            'created_at' => $now
        );

        set_transient($this->get_otp_option_name($email), $otp_data, $expiration_minutes * 60);

        return true;
    }

    /**
     * Recupera código OTP válido para um email
     */
    private function get_valid_otp_code($email) {
        $otp_data = get_transient($this->get_otp_option_name($email));

        if (!$otp_data || !is_array($otp_data) || !isset($otp_data['hash'])) {
            return false;
        }

        // Verifica se não expirou
        if (empty($otp_data['expires_at']) || $otp_data['expires_at'] < time()) {
            $this->delete_otp_code($email);
            return false;
        }

        return $otp_data;
    }

    /**
     * Remove o código OTP (usado após o acerto e ao estourar as tentativas)
     */
    private function delete_otp_code($email) {
        delete_transient($this->get_otp_option_name($email));
    }

    /**
     * Chave do bloqueio temporário do email
     */
    private function get_lockout_key($email) {
        return 'lkn_wcip_otp_lockout_' . $this->get_email_key($email);
    }

    /**
     * Verifica se o email está bloqueado por excesso de tentativas
     */
    private function is_locked_out($email) {
        return (bool) get_transient($this->get_lockout_key($email));
    }

    /**
     * Bloqueia o email por um período (renova o código: o usuário precisa pedir um novo depois)
     */
    private function lock_out($email) {
        set_transient($this->get_lockout_key($email), time(), self::LOCKOUT_SECONDS);
        $this->delete_otp_code($email);
    }

    /**
     * Conta um evento numa janela deslizante simples (option/transient com o TTL restante)
     *
     * @return bool false quando o limite da janela já foi atingido
     */
    private function register_event($key, $limit, $window) {
        $entry = get_transient($key);
        $count = 0;
        $expires = time() + $window;

        if (is_array($entry) && isset($entry['count'], $entry['expires'])) {
            $count = (int) $entry['count'];
            $expires = (int) $entry['expires'];
        }

        if ($expires < time()) {
            $count = 0;
            $expires = time() + $window;
        }

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, array('count' => $count + 1, 'expires' => $expires), max(1, $expires - time()));

        return true;
    }

    /**
     * IP do cliente (usado apenas para limitar tentativas; não é persistido)
     */
    private function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return $ip === '' ? 'unknown' : $ip;
    }

    /**
     * Registra falha de verificação por IP
     */
    private function register_ip_failure() {
        $this->register_event('lkn_wcip_otp_fail_ip_' . md5($this->get_client_ip()), self::IP_FAIL_LIMIT, self::IP_FAIL_WINDOW_SECONDS);
    }

    /**
     * Verifica se o IP estourou o limite de falhas
     */
    private function is_ip_blocked() {
        $entry = get_transient('lkn_wcip_otp_fail_ip_' . md5($this->get_client_ip()));

        return is_array($entry) && isset($entry['count']) && (int) $entry['count'] >= self::IP_FAIL_LIMIT;
    }

    /**
     * Remove códigos salvos por versões anteriores (texto plano em options)
     */
    private function cleanup_legacy_codes() {
        if (get_transient('lkn_wcip_otp_cleanup_done')) {
            return;
        }

        global $wpdb;

        // Options do formato antigo: lkn_wcip_otp_<md5 do email>, com o código em texto plano.
        // O REGEXP restrito a 32 caracteres hexadecimais não pega as configurações
        // (lkn_wcip_otp_email_*) nem os transients usados agora (_transient_*).
        $legacy_options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name REGEXP %s",
                '^lkn_wcip_otp_[0-9a-f]{32}$'
            )
        );

        foreach ($legacy_options as $option_name) {
            $legacy_data = get_option($option_name);

            // Só remove o que realmente é um código OTP antigo
            if (is_array($legacy_data) && isset($legacy_data['code'])) {
                delete_option($option_name);
            }
        }

        set_transient('lkn_wcip_otp_cleanup_done', 1, DAY_IN_SECONDS);
    }

    /**
     * Envia email com código OTP
     */
    private function send_otp_email($email, $code) {
        $subject = sprintf(
            /* translators: %s: OTP verification code */
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
        $email = sanitize_email(isset($parameters['email']) ? $parameters['email'] : '');

        if (!$this->is_otp_enabled()) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('OTP não está ativado', 'wc-invoice-payment')
            ), 403);
        }

        if (!$email || !is_email($email)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Informe um email válido.', 'wc-invoice-payment')
            ), 400);
        }

        $this->cleanup_legacy_codes();

        if (!$this->check_send_limits($email)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Aguarde alguns instantes antes de solicitar um novo código.', 'wc-invoice-payment')
            ), 429);
        }

        // O código é sempre gerado e guardado, inclusive para email sem conta. Assim envio e
        // verificação se comportam igual em qualquer caso e o formulário não serve para
        // descobrir se um email (inclusive de administrador) está cadastrado.
        // O código é guardado apenas como hash: um reenvio emite um código novo (o anterior
        // deixa de valer) e reinicia o contador de tentativas.
        $code = $this->generate_otp_code();
        $this->save_otp_code($email, $code);

        // Modo "apenas login": sem conta não há para quem enviar (o código fica só no storage).
        $silent = $this->is_login_only_mode() && !email_exists($email);

        if (!$silent && !$this->send_otp_email($email, $code)) {
            $this->delete_otp_code($email);

            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Erro ao enviar código.', 'wc-invoice-payment')
            ), 500);
        }

        // O throttle de reenvio só é consumido no sucesso (falha de email permite tentar de novo).
        $this->mark_sent($email);

        return new \WP_REST_Response(array(
            'success' => true,
            // A mensagem não pode depender da existência da conta.
            'message' => $this->is_login_only_mode()
                ? __('Se este email estiver cadastrado, você receberá um código de acesso.', 'wc-invoice-payment')
                : __('Código enviado para seu email.', 'wc-invoice-payment')
        ), 200);
    }

    /**
     * Limita o envio de códigos por email e por IP
     */
    private function check_send_limits($email) {
        $email_key = $this->get_email_key($email);

        if (get_transient('lkn_wcip_otp_throttle_' . $email_key)) {
            return false;
        }

        if (!$this->register_event('lkn_wcip_otp_sends_' . $email_key, self::SEND_LIMIT_EMAIL, self::SEND_WINDOW_SECONDS)) {
            return false;
        }

        if (!$this->register_event('lkn_wcip_otp_sends_ip_' . md5($this->get_client_ip()), self::SEND_LIMIT_IP, self::SEND_WINDOW_SECONDS)) {
            return false;
        }

        return true;
    }

    /**
     * Marca o envio (intervalo mínimo entre reenvios do mesmo email)
     */
    private function mark_sent($email) {
        set_transient('lkn_wcip_otp_throttle_' . $this->get_email_key($email), 1, self::RESEND_THROTTLE_SECONDS);
    }

    /**
     * Endpoint: Verifica código OTP
     */
    public function verifyOtpCode($request) {
        $parameters = $request->get_params();
        $email = sanitize_email(isset($parameters['email']) ? $parameters['email'] : '');
        $code = sanitize_text_field(isset($parameters['code']) ? $parameters['code'] : '');

        if (!$this->is_otp_enabled()) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('OTP não está ativado', 'wc-invoice-payment')
            ), 403);
        }

        if (!$email || !is_email($email)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Informe um email válido.', 'wc-invoice-payment')
            ), 400);
        }

        if ($this->is_ip_blocked()) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Muitas tentativas inválidas. Tente novamente mais tarde.', 'wc-invoice-payment')
            ), 429);
        }

        if ($this->is_locked_out($email)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => __('Muitas tentativas inválidas. Solicite um novo código mais tarde.', 'wc-invoice-payment')
            ), 429);
        }

        $result = $this->process_otp_login($email, $code);

        $status = 400;

        if (!empty($result['success'])) {
            $status = 200;
        } elseif (!empty($result['locked'])) {
            $status = 429;
        }

        return new \WP_REST_Response($result, $status);
    }

    /**
     * Valida código OTP.
     *
     * Cada erro consome uma tentativa; ao atingir o limite o código é invalidado
     * e o email fica bloqueado por LOCKOUT_SECONDS.
     */
    public function verify_otp_code($email, $code) {
        $otp_data = $this->get_valid_otp_code($email);
        
        if (!$otp_data) {
            return false;
        }

        $matches = hash_equals((string) $otp_data['hash'], $this->hash_otp_code($code))
            && isset($otp_data['email'])
            && $otp_data['email'] === $email;

        if (!$matches) {
            $this->register_verify_failure($email, $otp_data);
            return false;
        }

        // Código de uso único
        $this->delete_otp_code($email);
        delete_transient('lkn_wcip_otp_throttle_' . $this->get_email_key($email));

        return true;
    }

    /**
     * Contabiliza uma tentativa inválida (invalida o código no limite)
     */
    private function register_verify_failure($email, array $otp_data) {
        $this->register_ip_failure();

        $attempts = isset($otp_data['attempts']) ? (int) $otp_data['attempts'] : 0;
        $attempts++;

        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            $this->lock_out($email);
            return;
        }

        $otp_data['attempts'] = $attempts;
        $remaining_seconds = max(1, (int) $otp_data['expires_at'] - time());

        set_transient($this->get_otp_option_name($email), $otp_data, $remaining_seconds);
    }

    /**
     * Tentativas restantes antes de invalidar o código
     */
    private function get_remaining_attempts($email) {
        $otp_data = $this->get_valid_otp_code($email);

        if (!$otp_data) {
            return 0;
        }

        $attempts = isset($otp_data['attempts']) ? (int) $otp_data['attempts'] : 0;

        return max(0, self::MAX_VERIFY_ATTEMPTS - $attempts);
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
        if ($this->is_locked_out($email)) {
            return array(
                'success' => false,
                'locked' => true,
                'message' => \__('Muitas tentativas inválidas. Solicite um novo código mais tarde.', 'wc-invoice-payment')
            );
        }

        // Verifica código
        if (!$this->verify_otp_code($email, $code)) {
            $remaining = $this->get_remaining_attempts($email);

            return array(
                'success' => false,
                'locked' => $this->is_locked_out($email),
                'remaining_attempts' => $remaining,
                'message' => $remaining > 0
                    /* translators: %d: número de tentativas restantes */
                    ? sprintf(\__('Código inválido ou expirado. Tentativas restantes: %d.', 'wc-invoice-payment'), $remaining)
                    : \__('Código inválido ou expirado.', 'wc-invoice-payment')
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
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML estático do plugin já escapado com esc_html_e
        echo $this->get_otp_form_html();
        
    }

    /**
     * Substitui apenas formulário de login
     */
    public function replace_login_form() {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML estático do plugin já escapado com esc_html_e
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
                <h2><?php esc_html_e('Acesse sua conta', 'wc-invoice-payment'); ?></h2>
                <p><?php esc_html_e('Digite seu email para receber um código de acesso', 'wc-invoice-payment'); ?></p>
                
                <!-- Formulário de email -->
                <form id="lkn-otp-email-form" class="lkn-otp-form" style="display: block;">
                    <div class="form-row">
                        <label for="lkn_otp_email"><?php esc_html_e('Seu email', 'wc-invoice-payment'); ?> <span class="required">*</span></label>
                        <input type="email" id="lkn_otp_email" name="email" class="input-text" required />
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="wp-element-button" name="otp_send_code" id="lkn-wc-otp-send-code">
                            <?php esc_html_e('Enviar código', 'wc-invoice-payment'); ?>
                        </button>
                    </div>
                </form>

                <!-- Formulário de código -->
                <form id="lkn-otp-code-form" class="lkn-otp-form" style="display: none;">
                    <div class="form-row">
                        <label for="lkn_otp_code"><?php esc_html_e('Código de acesso', 'wc-invoice-payment'); ?> <span class="required">*</span></label>
                        <input type="text" inputmode="numeric" id="lkn_otp_code" name="code" class="input-text" maxlength="6" pattern="[0-9]{6}" required />
                        <small style="text-align: center;">
                            <?php esc_html_e('Digite o código de 6 dígitos enviado para seu email', 'wc-invoice-payment'); ?><br>
                            <a href="#" id="lkn-otp-back"><?php esc_html_e('Voltar e cancelar.', 'wc-invoice-payment'); ?></a>
                        </small>
                    </div>
                    
                    <div class="form-row">
                        <button type="submit" class="wp-element-button" name="otp_verify_code">
                            <?php esc_html_e('Verificar código', 'wc-invoice-payment'); ?>
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
            '1.1.0'
        );

        // Enfileira JavaScript
        \wp_enqueue_script(
            'wc-invoice-payment-otp',
            \plugin_dir_url(__DIR__) . 'Public/js/wc-invoice-payment-otp.js',
            array('jquery', 'wp-api-fetch'),
            '1.1.0',
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
