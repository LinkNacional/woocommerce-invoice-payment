<?php

/**
 * Handles the logic for communicating with the existing PDF templates for invoices.
 *
 * @see       https://www.linknacional.com/
 * @since      1.2.0
 */

/**
 * @author     Link Nacional
 */
final class Wc_Payment_Invoice_Pdf_Templates {
    /**
     * The ID of this plugin.
     *
     * @since    1.2.0
     *
     * @var string the ID of this plugin
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.2.0
     *
     * @var string the current version of this plugin
     */
    private $version;

    /**
     * @since    1.2.0
     *
     * @var string the current version of this plugin
     */
    private $templates_root_dir;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.2.0
     *
     * @param string $plugin_name the name of this plugin
     * @param string $version     the version of this plugin
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        $this->templates_root_dir = WC_PAYMENT_INVOICE_ROOT_DIR . 'includes/templates';
    }

    public function get_templates_list(): array {
        $templates_json_paths = glob("{$this->templates_root_dir}/*/template.json");

        WP_Filesystem();
    
        // Verifica se o sistema de arquivos foi inicializado corretamente
        global $wp_filesystem;
        if (!$wp_filesystem) {
            return array(); // Retorna um array vazio se não foi possível inicializar o sistema de arquivos
        }   

        return array_map(function (string $template_json_path) use ($wp_filesystem): array {
            $template_info = json_decode($wp_filesystem->get_contents($template_json_path));
            $template_id = basename(dirname($template_json_path));
            $template_preview_url = WC_PAYMENT_INVOICE_ROOT_URL . "includes/templates/$template_id/preview.webp";

            return array(
                'id' => $template_id,
                'preview_url' => $template_preview_url,
                'friendly_name' => $template_info->friendly_name . " ($template_id)"
            );
        }, $templates_json_paths);
    }
    
}
