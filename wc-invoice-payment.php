<?php

/**
 * The plugin bootstrap file.
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @see              https://www.linknacional.com/
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Invoice Payment for WooCommerce
 * Plugin URI:        https://www.linknacional.com/wordpress/plugins/
 * Description:       Invoice payment generation and management for WooCommerce.
 * Version:           1.6.0
 * Author:            Link Nacional
 * Author URI:        https://www.linknacional.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-invoice-payment
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    exit;
}

/*
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('WC_PAYMENT_INVOICE_VERSION', '1.6.0');
define('WC_PAYMENT_INVOICE_TRANSLATION_PATH', plugin_dir_path(__FILE__) . 'languages/');
define('WC_PAYMENT_INVOICE_ROOT_DIR', plugin_dir_path(__FILE__));
define('WC_PAYMENT_INVOICE_ROOT_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wc-invoice-payment-activator.php.
 */
function activate_Wc_Payment_Invoice(): void
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-invoice-payment-activator.php';
    Wc_Payment_Invoice_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wc-invoice-payment-deactivator.php.
 */
function deactivate_Wc_Payment_Invoice(): void
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-invoice-payment-deactivator.php';
    Wc_Payment_Invoice_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wc_payment_invoice');
register_deactivation_hook(__FILE__, 'deactivate_wc_payment_invoice');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-wc-invoice-payment.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wc_payment_invoice(): void
{
    $plugin = new Wc_Payment_Invoice();
    $plugin->run();

    add_action('admin_notices', 'lkn_wcip_woocommerce_missing_notice');
}
run_wc_payment_invoice();

/**
 * WooCommerce missing notice.
 */
function lkn_wcip_woocommerce_missing_notice(): void
{
    include_once __DIR__ . '/admin/partials/wc-invoice-payment-admin-missing-woocommerce.php';
}
// TODO alterar configuração para input file do wordpress
/* $image_url = 'https://dummyimage.com/180x180/000/fff';

// Caminho onde deseja salvar a imagem localmente
$upload_dir = wp_upload_dir();
$image_path = WC_PAYMENT_INVOICE_ROOT_DIR . 'nome_da_imagem2.png';

// Baixar a imagem e salvar localmente
$image_data = file_get_contents($image_url);
if ($image_data !== false) {
    // Salvar a imagem no diretório desejado
    add_option('teste file put'. uniqid(), (WC_PAYMENT_INVOICE_ROOT_DIR));
    add_option('teste file put 2 '. uniqid(), json_encode($image_data));
    file_put_contents($image_path, $image_data);
    echo 'Imagem baixada e salva com sucesso em: ' . $image_path;
} else {
    echo 'Falha ao baixar a imagem.';
}


$image_path = WC_PAYMENT_INVOICE_ROOT_DIR . 'nome_da_imagem2.png';

// Função para converter a imagem para base64 e reduzir a qualidade até atingir o limite
function convert_to_base64_with_limit($image_path, $max_base64_length) {
    $quality = 9; // Qualidade inicial para PNG
    $new_width = 160; // Nova largura desejada inicial
    $base64_string = '';
    
    do {
        // Redimensionar a imagem novamente para garantir a menor largura possível
        $im = imagecreatefrompng($image_path);
        $source_width = imagesx($im);
        $source_height = imagesy($im);
        $new_height = ceil($source_height / $source_width * $new_width); // Calcula a nova altura proporcionalmente
        $thumb = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($thumb, $im, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);
        
        // Compressão da imagem com a qualidade atual
        ob_start();
        imagepng($thumb, null, $quality);
        $compressed_image_data = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($im);
        imagedestroy($thumb);

        // Codificação em base64
        $base64_image = base64_encode($compressed_image_data);
        $base64_string = 'data:image/png;base64,' . $base64_image;
        return $base64_string;

        // Verificação do tamanho
        if (strlen($base64_string) <= $max_base64_length) {
        }

        // Reduzir a qualidade ou as dimensões para tentar diminuir o tamanho
        $quality--;
        if ($quality < 0) {
            $quality = 9;
            $new_width -= 10; // Reduzir a largura em 10 pixels
        }

    } while (strlen($base64_string) > $max_base64_length && $new_width > 0);

    return false; // Retorna false se não for possível reduzir o tamanho abaixo do limite
}

$max_base64_length = strlen('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALQAAAC0BAMAAADP4xsBAAAAG1BMVEUAAAD///9/f38/Pz8fHx+/v7/f399fX1+fn5+MXBRAAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABMUlEQVRoge3UPUvDUBTG8WMSm45+hCIiHbMYOkaJnQtt6iomqGMRoo6lUvzannuj0MHb5Lj6/w2XkIfzcPMqAgAAAAAAAAAAAACQKNNlVn3qelN9WMI+yXoiMl6Ws4XET+XrZnjYK3/R6VwPdjLKJGmGh33SJtLpcz16l6mu08Fhr6hw0624hqWuc39Ob8UkEJrKdXq0kbgRd70jrU1X2pIFQmt1sn/bF3L2M90WSR0MjdXSVg/d9Knbbbr63vRvobH69kIf2cF0Wx8JbdXuk8gPrnneHAlt1fd+zr0EJ+5UUrdFMDRWd1e70/XKbzpzL0kgNFa7LV37z+7Ob9q9JIHQWp1n+o+Q8VaihXRPK90GQmt1vK4e9d9zWT1bwoHKwq9/CAEAAAAAAAAAAADg//kCLKg/biwhg9AAAAAASUVORK5CYII=');

$logo_base64 = convert_to_base64_with_limit($image_path, $max_base64_length);
if ($logo_base64 !== false) {
    echo 'Imagem convertida para base64 com sucesso.';
} else {
    echo 'Não foi possível converter a imagem para base64 dentro do limite especificado.';
} */

/* echo '<img src="' . WC_PAYMENT_INVOICE_ROOT_URL.'nome_da_imagem2.png' . '" alt="Imagem convertida">'; */