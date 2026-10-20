<?php
/**
 * Bootstrap dos testes integrados (wp-phpunit) do Invoice Payment for WooCommerce.
 *
 * Fluxo:
 *   1. autoload do plugin
 *   2. wp-tests-config.php (constantes de DB + ABSPATH → WordPress do Local WP)
 *   3. `muplugins_loaded` → carrega o WooCommerce (sibling) e o nosso plugin
 *      ANTES do `init`, para que todos os hooks registrem.
 *   4. bootstrap do wp-phpunit (instala o banco de teste e carrega o WP)
 *
 * REGRA DE OURO: o WooCommerce DEVE ser carregado ANTES do nosso plugin.
 *
 * @package LknWc\WcInvoicePayment\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// 1. Arquivo de configuração dos testes.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
}

// 2. Polyfills de PHPUnit (Yoast).
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define(
		'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
		dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php'
	);
}

$wc_main_file     = dirname( __DIR__, 2 ) . '/woocommerce/woocommerce.php';
$plugin_main_file = dirname( __DIR__ ) . '/wc-invoice-payment.php';

/*
 * Opções pré-definidas antes do `init`. HPOS fica desligado de propósito: a
 * loja de teste usa o storage legado (posts), que não depende das tabelas
 * `wc_orders` criadas no install do WooCommerce.
 */
$GLOBALS['wp_tests_options'] = array_merge(
	isset( $GLOBALS['wp_tests_options'] ) ? $GLOBALS['wp_tests_options'] : array(),
	array(
		'woocommerce_currency'                        => 'BRL',
		'woocommerce_default_country'                 => 'BR:SP',
		'woocommerce_calc_taxes'                      => 'no',
		'woocommerce_custom_orders_table_enabled'     => 'no',
		'woocommerce_custom_orders_table_data_sync_enabled' => 'no',
		'woocommerce_enable_guest_checkout'           => 'yes',
		'woocommerce_onboarding_profile'              => array( 'skipped' => true ),
	)
);

require_once dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () use ( $wc_main_file, $plugin_main_file ) {
		if ( ! file_exists( $wc_main_file ) ) {
			fwrite( STDERR, sprintf( '[Tests Bootstrap] ERRO: WooCommerce não encontrado em: %s' . PHP_EOL, $wc_main_file ) );

			return;
		}

		// WooCommerce primeiro.
		require_once $wc_main_file;

		// Cria as tabelas do WooCommerce (o install do WP só cria as do core).
		// Usamos apenas `create_tables()` (não `install()`, que depende de
		// funções pluggable ainda não carregadas neste ponto).
		if ( class_exists( '\WC_Install' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			\WC_Install::create_tables();
		}

		// Depois nosso plugin.
		require_once $plugin_main_file;
	}
);

require_once dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';
