<?php
/**
 * Assinaturas: adaptação para produtos variáveis e ciclo de geração de faturas.
 *
 * Cobre a resolução da cadência (variação > produto pai > pedido), o registro da
 * assinatura na compra e o agendamento/geração da fatura seguinte.
 *
 * @package LknWc\WcInvoicePayment\Tests
 */

namespace LknWc\WcInvoicePayment\Tests;

use WC_Order;

final class SubscriptionVariableProductTest extends TestCase {

	/**
	 * Cria um produto variável com duas variações:
	 * - "mensal": assinatura própria (a cada 2 meses, limite 3)
	 * - "unico": pagamento único
	 *
	 * @return array{parent: \WC_Product_Variable, monthly: \WC_Product_Variation, one_time: \WC_Product_Variation}
	 */
	private function variable_subscription_product(): array {
		$parent = $this->create_variable_product(
			array(
				'name' => 'Assinatura variável',
				'meta' => array(
					'_lkn-wcip-subscription-product'     => 'on',
					'lkn_wcip_subscription_interval_number' => 1,
					'lkn_wcip_subscription_interval_type'   => 'year',
					'lkn_wcip_subscription_limit'           => 0,
				),
			)
		);

		$monthly = $this->create_variation(
			$parent,
			array(
				'sku'  => 'var-mensal',
				'meta' => array(
					'lkn_wcip_variation_payment_mode'        => 'subscription',
					'lkn_wcip_subscription_interval_number'  => 2,
					'lkn_wcip_subscription_interval_type'    => 'month',
					'lkn_wcip_subscription_limit'            => 3,
				),
			)
		);

		$one_time = $this->create_variation(
			$parent,
			array(
				'sku'  => 'var-unico',
				'meta' => array(
					'lkn_wcip_variation_payment_mode' => 'one_time',
				),
			)
		);

		return array(
			'parent'   => $parent,
			'monthly'  => $monthly,
			'one_time' => $one_time,
		);
	}

	/**
	 * Configuração de assinatura resolvida para um item de pedido.
	 *
	 * @param \WC_Product $product Produto/variação.
	 *
	 * @return array<string, mixed>
	 */
	private function config_for( $product ): array {
		$variation_id = $product instanceof \WC_Product_Variation ? $product->get_id() : 0;

		return $this->invoke_private(
			$this->subscription,
			'get_product_subscription_config',
			array( $product->get_parent_id() ?: $product->get_id(), $variation_id )
		);
	}

	/* --------------------------------------------------------------------- */
	/* Resolução da cadência                                                 */
	/* --------------------------------------------------------------------- */

	public function test_variation_with_its_own_cadence_wins_over_the_parent(): void {
		$products = $this->variable_subscription_product();
		$config   = $this->config_for( $products['monthly'] );

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 2, (int) $config['number'] );
		$this->assertSame( 'month', $config['type'] );
		$this->assertSame( 3, (int) $config['limit'] );
		$this->assertSame( $products['monthly']->get_id(), $config['source_id'] );
	}

	public function test_one_time_variation_overrides_a_subscription_parent(): void {
		$products = $this->variable_subscription_product();
		$config   = $this->config_for( $products['one_time'] );

		$this->assertFalse( $config['enabled'] );
	}

	public function test_variation_without_config_inherits_the_parent(): void {
		$parent = $this->create_variable_product(
			array(
				'meta' => array(
					'_lkn-wcip-subscription-product'        => 'on',
					'lkn_wcip_subscription_interval_number' => 3,
					'lkn_wcip_subscription_interval_type'   => 'week',
					'lkn_wcip_subscription_limit'           => 5,
				),
			)
		);

		$variation = $this->create_variation( $parent, array( 'sku' => 'var-herda' ) );
		$config    = $this->config_for( $variation );

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 3, (int) $config['number'] );
		$this->assertSame( 'week', $config['type'] );
		$this->assertSame( 5, (int) $config['limit'] );
		$this->assertSame( $parent->get_id(), $config['source_id'] );
	}

	public function test_variation_marked_as_subscription_without_cadence_falls_back_to_the_parent(): void {
		$parent = $this->create_variable_product(
			array(
				'meta' => array(
					'lkn_wcip_subscription_interval_number' => 6,
					'lkn_wcip_subscription_interval_type'   => 'month',
				),
			)
		);

		$variation = $this->create_variation(
			$parent,
			array(
				'meta' => array( 'lkn_wcip_variation_payment_mode' => 'subscription' ),
			)
		);

		$config = $this->config_for( $variation );

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 6, (int) $config['number'] );
		$this->assertSame( 'month', $config['type'] );
	}

	public function test_variable_product_without_subscription_flag_is_not_a_subscription(): void {
		$parent    = $this->create_variable_product();
		$variation = $this->create_variation( $parent, array( 'sku' => 'var-simples' ) );

		$this->assertFalse( $this->config_for( $variation )['enabled'] );
	}

	/* --------------------------------------------------------------------- */
	/* Registro da assinatura no pedido                                      */
	/* --------------------------------------------------------------------- */

	public function test_order_with_subscription_variation_registers_the_cadence_from_the_variation(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ) );

		$this->assertTrue( $this->subscription->order_contains_subscription( $order ) );

		$this->subscription->validate_product( $order->get_id() );

		$fresh = wc_get_order( $order->get_id() );

		$this->assertTrue( (bool) $fresh->get_meta( 'lkn_is_subscription' ) );
		$this->assertSame( 2, (int) $fresh->get_meta( 'lkn_wcip_subscription_interval_number' ) );
		$this->assertSame( 'month', $fresh->get_meta( 'lkn_wcip_subscription_interval_type' ) );
		$this->assertSame( 3, (int) $fresh->get_meta( 'lkn_wcip_subscription_limit' ) );
		$this->assertGreaterThan( time(), (int) $fresh->get_meta( 'lkn_wcip_subscription_next_due_date' ) );
		$this->assertTrue( (bool) $fresh->get_meta( 'lkn_wcip_subscription_already_processed' ) );

		$invoices = get_option( 'lkn_wcip_invoices', array() );
		$this->assertContains( $order->get_id(), array_map( 'intval', $invoices ) );
	}

	public function test_order_with_one_time_variation_is_not_flagged_as_subscription(): void {
		update_option( 'lkn_wcip_subscription_active_product_invoices', 'yes' );

		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['one_time'] ) );

		$this->assertFalse( $this->subscription->order_contains_subscription( $order ) );

		$this->subscription->validate_product( $order->get_id() );

		$fresh = wc_get_order( $order->get_id() );

		$this->assertEmpty( $fresh->get_meta( 'lkn_is_subscription' ) );
		$this->assertEmpty( $fresh->get_meta( 'lkn_wcip_subscription_interval_type' ) );
		$this->assertEmpty( $fresh->get_meta( 'lkn_wcip_subscription_limit' ) );

		// A fatura é registrada na lista, mas não é assinatura.
		$this->assertContains( $order->get_id(), array_map( 'intval', get_option( 'lkn_wcip_invoices', array() ) ) );
	}

	public function test_pending_order_does_not_register_the_subscription(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'pending' ) );

		$this->subscription->validate_product( $order->get_id() );

		$fresh = wc_get_order( $order->get_id() );

		$this->assertEmpty( $fresh->get_meta( 'lkn_is_subscription' ) );
		$this->assertEmpty( $fresh->get_meta( 'lkn_wcip_subscription_already_processed' ) );
	}

	public function test_quote_is_not_registered_as_subscription(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order(
			array( $products['monthly'] ),
			array( 'meta' => array( 'lkn_is_quote' => 'yes' ) )
		);

		$this->subscription->validate_product( $order->get_id() );

		$fresh = wc_get_order( $order->get_id() );

		$this->assertEmpty( $fresh->get_meta( 'lkn_is_subscription' ) );
	}



	public function test_legacy_order_with_unflagged_product_keeps_its_stored_interval(): void {
		$parent = $this->create_variable_product(
			array(
				'meta' => array(
					'lkn_wcip_subscription_interval_number' => 4,
					'lkn_wcip_subscription_interval_type'   => 'week',
				),
			)
		);

		// Produto sem o flag `_lkn-wcip-subscription-product` (lojista desmarcou depois).
		$variation = $this->create_variation( $parent, array( 'sku' => 'var-legado' ) );
		$order     = $this->create_order( array( $variation ) );

		$interval = $this->invoke_private( $this->subscription, 'resolve_order_interval', array( $order ) );

		$this->assertSame( 4, (int) $interval['number'] );
		$this->assertSame( 'week', $interval['type'] );
	}

	public function test_editing_the_variation_does_not_change_an_existing_subscription(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ) );

		$this->subscription->validate_product( $order->get_id() );

		// O lojista muda a cadência da variação no catálogo (2 meses -> 1 ano).
		update_post_meta( $products['monthly']->get_id(), 'lkn_wcip_subscription_interval_number', 1 );
		update_post_meta( $products['monthly']->get_id(), 'lkn_wcip_subscription_interval_type', 'year' );

		$next_due = $this->subscription->update_next_invoice_schedule( $order->get_id() );

		$fresh = wc_get_order( $order->get_id() );

		// A assinatura em andamento continua na cadência comprada (2 meses).
		$this->assertSame( 2, (int) $fresh->get_meta( 'lkn_wcip_subscription_interval_number' ) );
		$this->assertSame( 'month', $fresh->get_meta( 'lkn_wcip_subscription_interval_type' ) );
		$this->assertLessThan( strtotime( '+2 months', time() ), $next_due );
	}

	public function test_editing_the_subscription_in_the_admin_is_respected(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ) );

		$this->subscription->validate_product( $order->get_id() );

		// Tela de assinaturas do admin: grava o novo intervalo no próprio pedido.
		$fresh = wc_get_order( $order->get_id() );
		$fresh->update_meta_data( 'lkn_wcip_subscription_interval_number', 15 );
		$fresh->update_meta_data( 'lkn_wcip_subscription_interval_type', 'day' );
		$fresh->save();

		$next_due = $this->subscription->update_next_invoice_schedule( $order->get_id() );

		// 15 dias - 1 semana de antecedência (calcule_switch).
		$this->assertGreaterThan( strtotime( '+7 days', time() ), $next_due );
		$this->assertLessThan( strtotime( '+9 days', time() ), $next_due );
	}

	public function test_order_without_stored_interval_falls_back_to_the_product(): void {
		// Pedido antigo: assinatura registrada, mas sem o intervalo no meta do pedido.
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ) );

		$interval = $this->invoke_private( $this->subscription, 'resolve_order_interval', array( $order ) );

		$this->assertSame( 2, (int) $interval['number'] );
		$this->assertSame( 'month', $interval['type'] );
	}

	public function test_legacy_subscription_gets_the_interval_snapshot_on_the_next_schedule(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ) );

		// Pedido no formato antigo (2.13.2): assinatura marcada, sem o intervalo no pedido.
		$fresh = wc_get_order( $order->get_id() );
		$fresh->update_meta_data( 'lkn_is_subscription', true );
		$fresh->save();

		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( 'lkn_wcip_subscription_interval_number' ) );

		$this->subscription->update_next_invoice_schedule( $order->get_id() );

		$migrated = wc_get_order( $order->get_id() );

		$this->assertSame( 2, (int) $migrated->get_meta( 'lkn_wcip_subscription_interval_number' ) );
		$this->assertSame( 'month', $migrated->get_meta( 'lkn_wcip_subscription_interval_type' ) );

		// A partir daqui, editar a variação não muda mais esta assinatura.
		update_post_meta( $products['monthly']->get_id(), 'lkn_wcip_subscription_interval_number', 40 );
		update_post_meta( $products['monthly']->get_id(), 'lkn_wcip_subscription_interval_type', 'day' );

		$next_due = $this->subscription->update_next_invoice_schedule( $order->get_id() );

		$this->assertLessThan( strtotime( '+2 months', time() ), $next_due );
	}

	/* --------------------------------------------------------------------- */
	/* Agendamento                                                           */
	/* --------------------------------------------------------------------- */

	public function test_next_invoice_schedule_uses_the_variation_cadence(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ) );

		$this->subscription->validate_product( $order->get_id() );

		$next_due = $this->subscription->update_next_invoice_schedule( $order->get_id() );

		$this->assertIsInt( $next_due );

		// 2 meses (menos 2 semanas de antecedência, regra do calcule_switch).
		$expected_min = strtotime( '+1 month', time() );
		$expected_max = strtotime( '+2 months', time() );

		$this->assertGreaterThan( $expected_min, $next_due );
		$this->assertLessThan( $expected_max, $next_due );

		$schedule = get_option( 'lkn_wcip_subscription_schedule', array() );
		$this->assertArrayHasKey( $order->get_id(), $schedule );
	}

	public function test_manual_subscription_keeps_the_interval_stored_in_the_order(): void {
		$order = $this->create_order(
			array(),
			array(
				'meta' => array(
					'lkn_wcip_subscription_is_manual'      => true,
					'lkn_is_subscription'                  => true,
					'lkn_wcip_subscription_interval_number' => 10,
					'lkn_wcip_subscription_interval_type'   => 'day',
				),
			)
		);

		$next_due = $this->subscription->update_next_invoice_schedule( $order->get_id() );

		$this->assertIsInt( $next_due );

		// 10 dias de intervalo com a antecedência automática (1 semana do calcule_switch).
		$this->assertGreaterThan( strtotime( '+2 days', time() ), $next_due );
		$this->assertLessThan( strtotime( '+4 days', time() ), $next_due );
	}

	public function test_scheduling_does_not_stack_duplicate_events(): void {
		$due = time() + 3600;

		$this->subscription->schedule_next_invoice_generation( 987, $due );
		$this->subscription->schedule_next_invoice_generation( 987, $due );

		$events = $this->events_for( 'generate_invoice_event', 987 );

		$this->assertCount( 1, $events );
		$this->assertSame( $due, (int) $events[0]['timestamp'] );
	}

	public function test_rescheduling_moves_the_event_when_the_date_changes(): void {
		$first  = time() + 3600;
		$second = time() + 7200;

		$this->subscription->schedule_next_invoice_generation( 654, $first );
		$this->subscription->schedule_next_invoice_generation( 654, $second );

		$events = $this->events_for( 'generate_invoice_event', 654 );

		$this->assertCount( 1, $events );
		$this->assertSame( $second, (int) $events[0]['timestamp'] );
	}

	public function test_scheduling_keeps_the_recurring_invoice_expiration_event(): void {
		$daily = time() + 600;
		wp_schedule_event( $daily, 'daily', 'lkn_wcip_cron_hook', array( 321 ) );

		$this->subscription->schedule_next_invoice_generation( 321, time() + 5000 );

		$events = $this->events_for( 'lkn_wcip_cron_hook', 321 );
		$recurring = array_values(
			array_filter(
				$events,
				static function ( $event ) {
					return ! empty( $event['schedule'] );
				}
			)
		);

		$this->assertNotEmpty( $recurring, 'O evento diário de expiração foi perdido.' );
	}

	public function test_cancelling_a_subscription_keeps_the_recurring_expiration_event(): void {
		$order = $this->create_order();
		$id    = $order->get_id();

		wp_schedule_event( time() + 600, 'daily', 'lkn_wcip_cron_hook', array( $id ) );
		$this->subscription->schedule_next_invoice_generation( $id, time() + 5000 );

		$this->invoke_private( $this->subscription, 'unschedule_subscription_events', array( $id ) );

		$events    = $this->events_for( 'generate_invoice_event', $id );
		$recurring = array_values(
			array_filter(
				$this->events_for( 'lkn_wcip_cron_hook', $id ),
				static function ( $event ) {
					return ! empty( $event['schedule'] );
				}
			)
		);

		$this->assertCount( 0, $events );
		$this->assertCount( 1, $recurring );
	}

	/* --------------------------------------------------------------------- */
	/* Trava de geração                                                      */
	/* --------------------------------------------------------------------- */

	public function test_generation_lock_is_exclusive(): void {
		$this->assertTrue( $this->invoke_private( $this->subscription, 'acquire_generation_lock', array( 111 ) ) );
		$this->assertFalse( $this->invoke_private( $this->subscription, 'acquire_generation_lock', array( 111 ) ) );

		$this->invoke_private( $this->subscription, 'release_generation_lock', array( 111 ) );

		$this->assertTrue( $this->invoke_private( $this->subscription, 'acquire_generation_lock', array( 111 ) ) );
	}

	public function test_orphan_generation_lock_expires(): void {
		update_option( 'lkn_wcip_generation_lock_222', time() - 400, 'no' );

		$this->assertTrue( $this->invoke_private( $this->subscription, 'acquire_generation_lock', array( 222 ) ) );
	}

	/* --------------------------------------------------------------------- */
	/* Classificação do pedido                                               */
	/* --------------------------------------------------------------------- */

	public function test_partial_payment_child_order_is_not_a_subscription(): void {
		$products = $this->variable_subscription_product();

		$order = $this->create_order(
			array( $products['monthly'] ),
			array( 'meta' => array( '_wc_lkn_is_partial_order' => 'yes' ) )
		);

		$this->assertFalse( $this->invoke_private( $this->subscription, 'is_subscription_order', array( $order ) ) );
	}

	public function test_order_created_by_the_invoice_generator_is_not_a_checkout(): void {
		$generated = $this->create_order( array(), array( 'created_via' => '' ) );
		$checkout  = $this->create_order( array(), array( 'created_via' => 'checkout' ) );

		$this->assertFalse( $this->invoke_private( $this->subscription, 'is_customer_checkout_order', array( $generated ) ) );
		$this->assertTrue( $this->invoke_private( $this->subscription, 'is_customer_checkout_order', array( $checkout ) ) );
	}

	/* --------------------------------------------------------------------- */
	/* Cálculo de datas                                                      */
	/* --------------------------------------------------------------------- */

	public function test_unknown_interval_type_does_not_break_the_calculation(): void {
		$result = $this->subscription->calcule_switch( 'invalid', 1, time() );

		$this->assertSame( '', $result['next_due_date'] );
		$this->assertIsString( $result['time_removed'] );
	}

	public function test_calculate_next_due_date_applies_the_advance_option(): void {
		update_option( 'lkn_wcip_interval_number', 5 );
		update_option( 'lkn_wcip_interval_type', 'day' );

		$result = $this->subscription->calculate_next_due_date( 30, 'day' );

		// 30 dias - 5 dias de antecedência.
		$this->assertSame( '5 day', $result['time_removed'] );
		$this->assertGreaterThan( strtotime( '+24 days', time() ), $result['next_due_date'] );
		$this->assertLessThan( strtotime( '+26 days', time() ), $result['next_due_date'] );
	}

	public function test_advance_option_larger_than_the_interval_falls_back_to_the_automatic_rule(): void {
		update_option( 'lkn_wcip_interval_number', 60 );
		update_option( 'lkn_wcip_interval_type', 'day' );

		$result = $this->subscription->calculate_next_due_date( 1, 'month' );

		$this->assertSame( '3 days', $result['time_removed'] );
	}

	/* --------------------------------------------------------------------- */
	/* Geração da fatura seguinte                                            */
	/* --------------------------------------------------------------------- */

	public function test_generation_skips_orders_that_are_not_paid(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'on-hold' ) );

		$this->subscription->validate_product( $order->get_id() , true );
		$this->subscription->create_next_invoice( $order->get_id() );

		$this->assertCount( 0, $this->orders_created_from( $order->get_id() ) );
	}

	public function test_processing_order_generates_the_next_invoice(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'processing' ) );

		$this->subscription->validate_product( $order->get_id() );
		$this->subscription->create_next_invoice( $order->get_id() );

		$this->assertCount( 1, $this->orders_created_from( $order->get_id() ) );
	}

	public function test_generation_pauses_when_the_interval_cannot_be_resolved(): void {
		// Assinatura "órfã": produto inexistente e sem intervalo gravado no pedido.
		$order = $this->create_order(
			array(),
			array(
				'status' => 'completed',
				'meta'   => array( 'lkn_is_subscription' => true ),
			)
		);

		$schedule                     = get_option( 'lkn_wcip_subscription_schedule', array() );
		$schedule[ $order->get_id() ] = current_time( 'timestamp' ) - 60;
		update_option( 'lkn_wcip_subscription_schedule', $schedule );

		$this->subscription->create_next_invoice( $order->get_id() );

		$fresh = wc_get_order( $order->get_id() );

		$this->assertEmpty( $fresh->get_meta( 'lkn_wcip_last_generation' ) );
		$this->assertCount( 0, $this->orders_created_from( $order->get_id() ) );

		// Sem cadência a assinatura sai do backup (senão a nota se repetiria a cada 24h).
		$this->assertArrayNotHasKey(
			$order->get_id(),
			get_option( 'lkn_wcip_subscription_schedule', array() )
		);
	}

	public function test_completed_subscription_generates_the_next_invoice_and_reschedules(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'completed' ) );

		$this->subscription->validate_product( $order->get_id() );
		wp_clear_scheduled_hook( 'generate_invoice_event', array( $order->get_id() ) );

		$this->subscription->create_next_invoice( $order->get_id() );

		$children = $this->orders_created_from( $order->get_id() );

		$this->assertCount( 1, $children, 'A fatura do próximo ciclo não foi gerada.' );

		$child = reset( $children );

		$this->assertSame( $order->get_id(), (int) $child->get_meta( 'lkn_subscription_id' ) );
		$this->assertSame( 'pending', $child->get_status() );

		// O ciclo seguinte é reagendado (o evento atual é consumido ao rodar).
		$this->assertNotEmpty( $this->events_for( 'generate_invoice_event', $order->get_id() ) );
	}

	public function test_generation_respects_the_invoice_limit(): void {
		$parent = $this->create_variable_product(
			array(
				'meta' => array( '_lkn-wcip-subscription-product' => 'on' ),
			)
		);

		$variation = $this->create_variation(
			$parent,
			array(
				'meta' => array(
					'lkn_wcip_variation_payment_mode'       => 'subscription',
					'lkn_wcip_subscription_interval_number' => 1,
					'lkn_wcip_subscription_interval_type'   => 'month',
					'lkn_wcip_subscription_limit'           => 2,
				),
			)
		);

		$order = $this->create_order( array( $variation ), array( 'status' => 'completed' ) );

		$this->subscription->validate_product( $order->get_id() );

		// Já foram geradas 2 faturas: o limite foi atingido.
		$fresh = wc_get_order( $order->get_id() );
		$fresh->update_meta_data( 'lkn_wcip_subscription_initial_limit', 2 );
		$fresh->save();

		$this->subscription->create_next_invoice( $order->get_id() );

		$this->assertCount( 0, $this->orders_created_from( $order->get_id() ) );
	}


	/* --------------------------------------------------------------------- */
	/* Fluxo completo: checkout → pago → agendamento → próxima fatura         */
	/* --------------------------------------------------------------------- */

	public function test_paying_a_checkout_order_schedules_the_first_invoice(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'pending' ) );

		// Simula o gateway confirmando o pagamento (pix/boleto confirmam depois do checkout).
		$order->update_status( 'processing' );

		$registered = wc_get_order( $order->get_id() );

		$this->assertTrue( (bool) $registered->get_meta( 'lkn_is_subscription' ), 'A assinatura não foi registrada no pagamento.' );

		$order->update_status( 'completed' );

		$events = $this->events_for( 'generate_invoice_event', $order->get_id() );

		$this->assertNotEmpty( $events, 'A primeira fatura não foi agendada ao completar o pedido.' );

		$schedule = get_option( 'lkn_wcip_subscription_schedule', array() );
		$this->assertArrayHasKey( $order->get_id(), $schedule );
	}

	public function test_two_cron_runners_do_not_generate_the_invoice_twice(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'completed' ) );

		$this->subscription->validate_product( $order->get_id() );

		// Duas execuções seguidas do evento (HTTP cron + cron do servidor).
		$this->subscription->create_next_invoice( $order->get_id() );
		$this->subscription->create_next_invoice( $order->get_id() );

		$this->assertCount( 1, $this->orders_created_from( $order->get_id() ) );
	}

	public function test_admin_backup_check_generates_overdue_invoices(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'completed' ) );

		$this->subscription->validate_product( $order->get_id() );

		// Sem cron ativo e com a data de vencimento no passado.
		wp_clear_scheduled_hook( 'generate_invoice_event', array( $order->get_id() ) );
		wp_clear_scheduled_hook( 'lkn_wcip_cron_hook', array( $order->get_id() ) );

		// ATENÇÃO: o plugin comparar `current_time( 'timestamp' )` com o valor salvo,
		// então o vencimento no passado é escrito na mesma base de tempo.
		$schedule                     = get_option( 'lkn_wcip_subscription_schedule', array() );
		$schedule[ $order->get_id() ] = current_time( 'timestamp' ) - 120;
		update_option( 'lkn_wcip_subscription_schedule', $schedule );
		update_option( 'lkn_wcip_last_subscription_check', 0 );

		$this->subscription->check_pending_subscriptions();

		$this->assertCount( 1, $this->orders_created_from( $order->get_id() ) );
	}

	public function test_backup_check_does_not_run_more_than_once_a_day(): void {
		$order = $this->create_order( array(), array( 'status' => 'completed' ) );

		$schedule                     = get_option( 'lkn_wcip_subscription_schedule', array() );
		$schedule[ $order->get_id() ] = current_time( 'timestamp' ) - 120;
		update_option( 'lkn_wcip_subscription_schedule', $schedule );
		update_option( 'lkn_wcip_last_subscription_check', current_time( 'timestamp' ) - 60 );

		$this->subscription->check_pending_subscriptions();

		// Ainda dentro das 24h: nenhuma fatura foi gerada.
		$this->assertCount( 0, $this->orders_created_from( $order->get_id() ) );
	}

	/**
	 * Lojas que usam "processing" como estado final (produtos virtuais) também precisam
	 * cobrar a assinatura: o agendamento acontece em qualquer status pago.
	 */
	public function test_paid_order_in_processing_is_scheduled(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'pending' ) );

		// Pagamento confirmado; a loja mantém o pedido em "processing" (não marca como concluído).
		$order->update_status( 'processing' );

		$this->assertTrue( (bool) wc_get_order( $order->get_id() )->get_meta( 'lkn_is_subscription' ) );
		$this->assertNotEmpty(
			$this->events_for( 'generate_invoice_event', $order->get_id() ),
			'A assinatura não foi agendada no status pago "processing".'
		);
		$this->assertArrayHasKey( $order->get_id(), get_option( 'lkn_wcip_subscription_schedule', array() ) );
	}

	public function test_unpaid_order_is_not_scheduled(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'on-hold' ) );

		$this->assertEmpty( $this->events_for( 'generate_invoice_event', $order->get_id() ) );
	}

	public function test_cancelling_a_subscription_removes_only_its_events(): void {
		$products = $this->variable_subscription_product();
		$order    = $this->create_order( array( $products['monthly'] ), array( 'status' => 'completed' ) );

		$this->subscription->validate_product( $order->get_id() );
		$this->subscription->schedule_next_invoice_generation( $order->get_id(), time() + 3600 );

		$this->assertNotEmpty( $this->events_for( 'generate_invoice_event', $order->get_id() ) );

		$this->invoke_private( $this->subscription, 'unschedule_subscription_events', array( $order->get_id() ) );

		$this->assertCount( 0, $this->events_for( 'generate_invoice_event', $order->get_id() ) );
	}

	/* --------------------------------------------------------------------- */
	/* Helpers                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Eventos do cron para um hook/args.
	 *
	 * @param string $hook   Hook.
	 * @param int    $arg    Argumento (ID do pedido).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function events_for( string $hook, int $arg ): array {
		$events = array();

		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			if ( ! isset( $hooks[ $hook ] ) ) {
				continue;
			}

			foreach ( $hooks[ $hook ] as $event ) {
				if ( isset( $event['args'][0] ) && (int) $event['args'][0] === $arg ) {
					$events[] = array(
						'timestamp' => (int) $timestamp,
						'schedule'  => isset( $event['schedule'] ) ? $event['schedule'] : false,
						'args'      => $event['args'],
					);
				}
			}
		}

		return $events;
	}

	/**
	 * Faturas geradas a partir de uma assinatura.
	 *
	 * @param int $order_id Pedido de assinatura.
	 *
	 * @return array<int, WC_Order>
	 */
	private function orders_created_from( int $order_id ): array {
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'meta_key'   => 'lkn_subscription_id',
				'meta_value' => $order_id,
			)
		);

		return is_array( $orders ) ? $orders : array();
	}
}
