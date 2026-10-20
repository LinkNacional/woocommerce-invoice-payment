<?php
/**
 * Base dos testes integrados do plugin.
 *
 * @package LknWc\WcInvoicePayment\Tests
 */

namespace LknWc\WcInvoicePayment\Tests;

use WC_Order;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_UnitTestCase;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceLoader;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceOtpEmail;
use LknWc\WcInvoicePayment\Includes\WcPaymentInvoiceSubscription;

abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Emails capturados pelo mock de `wp_mail()`.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	protected $mails = array();

	/**
	 * Instância de assinatura usada pelos testes.
	 *
	 * @var WcPaymentInvoiceSubscription
	 */
	protected $subscription;

	protected function setUp(): void {
		parent::setUp();

		$this->mails        = array();
		$this->subscription = new WcPaymentInvoiceSubscription();

		// Usuário anônimo por padrão: os endpoints OTP são públicos.
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	}

	protected function tearDown(): void {
		remove_all_filters( 'pre_wp_mail' );
		unset( $_SERVER['REMOTE_ADDR'] );
		parent::tearDown();
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Instancia a classe de OTP.
	 *
	 * @return WcPaymentInvoiceOtpEmail
	 */
	protected function otp(): WcPaymentInvoiceOtpEmail {
		return new WcPaymentInvoiceOtpEmail( new WcPaymentInvoiceLoader() );
	}

	/**
	 * Chama um método privado/protegido.
	 *
	 * @param object $object Instância.
	 * @param string $method Método.
	 * @param array  $args   Argumentos.
	 *
	 * @return mixed
	 */
	protected function invoke_private( $object, string $method, array $args = array() ) {
		$reflection = new \ReflectionMethod( $object, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $object, $args );
	}

	/**
	 * Passa a capturar os emails enviados por `wp_mail()`.
	 */
	protected function intercept_mail(): void {
		$this->mails = array();

		add_filter(
			'pre_wp_mail',
			function ( $return, $atts ) {
				$this->mails[] = $atts;

				return true;
			},
			10,
			2
		);
	}

	/**
	 * Último email capturado.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function last_mail() {
		return empty( $this->mails ) ? null : end( $this->mails );
	}

	/**
	 * Extrai o código de 6 dígitos do último email enviado.
	 *
	 * @return string
	 */
	protected function code_from_last_mail(): string {
		$mail = $this->last_mail();

		$this->assertNotNull( $mail, 'Nenhum email foi enviado.' );

		$plain = wp_strip_all_tags( (string) $mail['message'] );

		$this->assertSame( 1, preg_match( '/\b(\d{6})\b/', $plain, $matches ), 'Código OTP não encontrado no email: ' . $plain );

		return $matches[1];
	}

	/**
	 * Cria um produto simples.
	 *
	 * @param array<string, mixed> $args Name, price, meta.
	 *
	 * @return WC_Product_Simple
	 */
	protected function create_simple_product( array $args = array() ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( $args['name'] ?? 'Produto de teste' );
		$product->set_status( 'publish' );
		$product->set_regular_price( (string) ( $args['price'] ?? 10 ) );
		$product->save();

		foreach ( $args['meta'] ?? array() as $key => $value ) {
			update_post_meta( $product->get_id(), $key, $value );
		}

		return $product;
	}

	/**
	 * Cria um produto variável.
	 *
	 * @param array<string, mixed> $args Name + meta do pai.
	 *
	 * @return WC_Product_Variable
	 */
	protected function create_variable_product( array $args = array() ): WC_Product_Variable {
		$product = new WC_Product_Variable();
		$product->set_name( $args['name'] ?? 'Produto variável de teste' );
		$product->set_status( 'publish' );
		$product->save();

		foreach ( $args['meta'] ?? array() as $key => $value ) {
			update_post_meta( $product->get_id(), $key, $value );
		}

		return $product;
	}

	/**
	 * Cria uma variação do produto variável.
	 *
	 * @param WC_Product_Variable  $parent Produto pai.
	 * @param array<string, mixed> $args   Price + meta da variação.
	 *
	 * @return WC_Product_Variation
	 */
	protected function create_variation( WC_Product_Variable $parent, array $args = array() ): WC_Product_Variation {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_status( 'publish' );
		$variation->set_regular_price( (string) ( $args['price'] ?? 10 ) );
		$variation->set_sku( $args['sku'] ?? '' );
		$variation->save();

		foreach ( $args['meta'] ?? array() as $key => $value ) {
			update_post_meta( $variation->get_id(), $key, $value );
		}

		return $variation;
	}

	/**
	 * Cria um pedido com os produtos informados.
	 *
	 * @param array<int, mixed>    $products Produtos/variações.
	 * @param array<string, mixed> $args     status, customer_id, created_via, meta.
	 *
	 * @return WC_Order
	 */
	protected function create_order( array $products = array(), array $args = array() ): WC_Order {
		$order = wc_create_order(
			array(
				'status'      => $args['status'] ?? 'processing',
				'customer_id' => $args['customer_id'] ?? 0,
			)
		);

		foreach ( $products as $product ) {
			$order->add_product( $product, 1 );
		}

		$order->set_billing_email( $args['billing_email'] ?? 'cliente@example.org' );
		$order->set_billing_first_name( 'Cliente' );
		$order->set_billing_last_name( 'Teste' );
		$order->set_created_via( $args['created_via'] ?? 'checkout' );

		foreach ( $args['meta'] ?? array() as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}

		$order->calculate_totals();
		$order->save();

		return $order;
	}
}
