<?php
/**
 * Segurança da autenticação OTP por email.
 *
 * Cobre o relato do BugShield: rotas públicas de OTP com código de 6 dígitos,
 * sem limite de tentativas e com login automático no acerto.
 *
 * @package LknWc\WcInvoicePayment\Tests
 */

namespace LknWc\WcInvoicePayment\Tests;

use WP_REST_Request;

final class OtpEmailSecurityTest extends TestCase {

	private const EMAIL = 'cliente@example.org';

	private function enable_otp( string $mode = 'login_only' ): void {
		update_option( 'lkn_wcip_otp_email_enable_type', $mode );
	}

	private function user(): int {
		return self::factory()->user->create(
			array(
				'user_email' => self::EMAIL,
				'user_login' => 'cliente_otp',
				'role'       => 'customer',
			)
		);
	}

	/**
	 * @return \WP_REST_Response
	 */
	private function send( string $email ) {
		$request = new WP_REST_Request( 'POST', '/invoice_payments/send_otp_code' );
		$request->set_param( 'email', $email );

		return $this->otp()->sendOtpCode( $request );
	}

	/**
	 * @return \WP_REST_Response
	 */
	private function verify( string $email, string $code ) {
		$request = new WP_REST_Request( 'POST', '/invoice_payments/verify_otp_code' );
		$request->set_param( 'email', $email );
		$request->set_param( 'code', $code );

		return $this->otp()->verifyOtpCode( $request );
	}

	private function stored_code_option( string $email ): string {
		return 'lkn_wcip_otp_' . md5( $email );
	}

	/* --------------------------------------------------------------------- */
	/* Rotas                                                                 */
	/* --------------------------------------------------------------------- */

	public function test_routes_refuse_requests_when_otp_is_disabled(): void {
		update_option( 'lkn_wcip_otp_email_enable_type', 'disabled' );

		do_action( 'rest_api_init' );

		$request = new WP_REST_Request( 'POST', '/invoice_payments/send_otp_code' );
		$request->set_param( 'email', self::EMAIL );

		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_routes_exist_and_reject_malformed_code(): void {
		$this->enable_otp();
		$this->user();

		do_action( 'rest_api_init' );

		$this->assertArrayHasKey( '/invoice_payments/send_otp_code', rest_get_server()->get_routes() );
		$this->assertArrayHasKey( '/invoice_payments/verify_otp_code', rest_get_server()->get_routes() );

		$request = new WP_REST_Request( 'POST', '/invoice_payments/verify_otp_code' );
		$request->set_param( 'email', self::EMAIL );
		$request->set_param( 'code', 'abc123' );

		$this->assertSame( 400, rest_do_request( $request )->get_status() );

		$request->set_param( 'code', '1234567' );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}

	/* --------------------------------------------------------------------- */
	/* Fluxo feliz                                                           */
	/* --------------------------------------------------------------------- */

	public function test_valid_code_logs_the_user_in(): void {
		$this->enable_otp();
		$user_id = $this->user();
		$this->intercept_mail();

		$send = $this->send( self::EMAIL );

		$this->assertSame( 200, $send->get_status() );
		$this->assertTrue( $send->get_data()['success'] );

		$code   = $this->code_from_last_mail();
		$verify = $this->verify( self::EMAIL, $code );

		$this->assertSame( 200, $verify->get_status() );
		$this->assertTrue( $verify->get_data()['success'] );
		$this->assertSame( $user_id, get_current_user_id() );
	}

	public function test_code_is_single_use(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$code = $this->code_from_last_mail();

		$this->assertSame( 200, $this->verify( self::EMAIL, $code )->get_status() );

		wp_set_current_user( 0 );
		$this->assertFalse( get_transient( $this->stored_code_option( self::EMAIL ) ) );

		$replay = $this->verify( self::EMAIL, $code );

		$this->assertSame( 400, $replay->get_status() );
		$this->assertFalse( $replay->get_data()['success'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	/* --------------------------------------------------------------------- */
	/* Armazenamento do código                                               */
	/* --------------------------------------------------------------------- */

	public function test_code_is_stored_hashed_and_never_in_options(): void {
		global $wpdb;

		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$code = $this->code_from_last_mail();

		$stored = get_transient( $this->stored_code_option( self::EMAIL ) );

		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( 'hash', $stored );
		$this->assertArrayNotHasKey( 'code', $stored );
		$this->assertStringNotContainsString( $code, wp_json_encode( $stored ) );

		// Nenhuma option do banco guarda o código em texto plano.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s",
				'%' . $wpdb->esc_like( $code ) . '%'
			)
		);

		$this->assertSame( '0', (string) $found );
	}

	public function test_code_expires_after_the_configured_window(): void {
		$this->enable_otp();
		update_option( 'lkn_wcip_otp_email_expiration_time', 1 );
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$code = $this->code_from_last_mail();

		// Simula a passagem do tempo: o transient vence e o código deixa de valer.
		$stored = get_transient( $this->stored_code_option( self::EMAIL ) );
		$stored['expires_at'] = time() - 5;
		set_transient( $this->stored_code_option( self::EMAIL ), $stored, 60 );

		$response = $this->verify( self::EMAIL, $code );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 0, get_current_user_id() );
	}

	/* --------------------------------------------------------------------- */
	/* Força bruta                                                           */
	/* --------------------------------------------------------------------- */

	public function test_five_wrong_guesses_invalidate_the_code_and_lock_the_email(): void {
		$this->enable_otp();
		$user_id = $this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$code = $this->code_from_last_mail();
		$wrong = $code === '000000' ? '111111' : '000000';

		for ( $attempt = 1; $attempt <= 4; $attempt++ ) {
			$response = $this->verify( self::EMAIL, $wrong );

			$this->assertSame( 400, $response->get_status(), 'Tentativa ' . $attempt );
			$this->assertFalse( $response->get_data()['success'] );
			$this->assertSame( 0, get_current_user_id() );
		}

		// Quinta tentativa: estoura o orçamento e cai no bloqueio.
		$fifth = $this->verify( self::EMAIL, $wrong );

		$this->assertSame( 429, $fifth->get_status() );
		$this->assertTrue( $fifth->get_data()['locked'] );

		// Código invalidado: o código correto não serve mais.
		$this->assertFalse( get_transient( $this->stored_code_option( self::EMAIL ) ) );

		$final = $this->verify( self::EMAIL, $code );

		$this->assertSame( 429, $final->get_status() );
		$this->assertFalse( $final->get_data()['success'] );
		$this->assertSame( 0, get_current_user_id() );
		$this->assertNotSame( $user_id, get_current_user_id() );
		$this->assertStringContainsString( 'Muitas tentativas', $final->get_data()['message'] );

		// O envio continua liberado (bloquear o envio permitiria trancar a conta de
		// terceiros), mas o código novo não pode ser verificado durante o bloqueio.
		delete_transient( 'lkn_wcip_otp_throttle_' . md5( self::EMAIL ) );

		$this->assertSame( 200, $this->send( self::EMAIL )->get_status() );
		$this->assertSame( 429, $this->verify( self::EMAIL, $this->code_from_last_mail() )->get_status() );
	}

	public function test_wrong_guess_reports_remaining_attempts(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$code  = $this->code_from_last_mail();
		$wrong = $code === '000000' ? '111111' : '000000';

		$first = $this->verify( self::EMAIL, $wrong );

		$this->assertSame( 400, $first->get_status() );
		$this->assertSame( 4, $first->get_data()['remaining_attempts'] );
	}

	public function test_guessing_cannot_exceed_the_attempt_budget_within_the_code_lifetime(): void {
		$this->enable_otp();
		update_option( 'lkn_wcip_otp_email_expiration_time', 60 );
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$code  = $this->code_from_last_mail();
		$wrong = $code === '000000' ? '111111' : '000000';

		// 5 tentativas erradas: lockout de 15 minutos. Pedir um código novo não devolve
		// orçamento de tentativas enquanto o bloqueio estiver ativo.
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$this->verify( self::EMAIL, $wrong );
		}

		delete_transient( 'lkn_wcip_otp_throttle_' . md5( self::EMAIL ) );

		$this->assertSame( 200, $this->send( self::EMAIL )->get_status() );
		$this->assertSame( 429, $this->verify( self::EMAIL, $wrong )->get_status() );
		$this->assertSame( 429, $this->verify( self::EMAIL, $this->code_from_last_mail() )->get_status() );
	}

	public function test_ip_is_blocked_after_repeated_failures(): void {
		$this->enable_otp();
		$this->intercept_mail();

		$wrong = '000000';

		for ( $i = 0; $i < 6; $i++ ) {
			$email = 'vitima' . $i . '@example.org';

			self::factory()->user->create(
				array(
					'user_email' => $email,
					'user_login' => 'vitima' . $i,
					'role'       => 'customer',
				)
			);

			$this->send( $email );

			for ( $attempt = 0; $attempt < 5; $attempt++ ) {
				$this->verify( $email, $wrong );
			}
		}

		$fresh = 'vitima_final@example.org';
		self::factory()->user->create(
			array(
				'user_email' => $fresh,
				'user_login' => 'vitima_final',
				'role'       => 'customer',
			)
		);

		$this->assertSame( 429, $this->verify( $fresh, '111111' )->get_status() );
	}

	/* --------------------------------------------------------------------- */
	/* Reenvio e enumeração                                                  */
	/* --------------------------------------------------------------------- */

	public function test_resend_is_throttled(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$this->assertSame( 200, $this->send( self::EMAIL )->get_status() );
		$this->assertCount( 1, $this->mails );

		$second = $this->send( self::EMAIL );

		$this->assertSame( 429, $second->get_status() );
		$this->assertCount( 1, $this->mails );
	}

	public function test_new_code_invalidates_the_previous_one(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$first_code = $this->code_from_last_mail();

		// Libera o throttle e pede um novo código.
		delete_transient( 'lkn_wcip_otp_throttle_' . md5( self::EMAIL ) );
		$this->send( self::EMAIL );
		$second_code = $this->code_from_last_mail();

		$this->assertNotSame( $first_code, $second_code );

		$response = $this->verify( self::EMAIL, $first_code );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_unknown_email_does_not_reveal_account_existence(): void {
		$this->enable_otp( 'login_only' );
		$this->user();
		$this->intercept_mail();

		$existing    = $this->send( self::EMAIL );
		$existing_ok = count( $this->mails );

		$unknown    = $this->send( 'nao_existe@example.org' );
		$unknown_ok = count( $this->mails );

		// Mesma resposta nos dois casos (status, corpo e mensagem).
		$this->assertSame( $existing->get_status(), $unknown->get_status() );
		$this->assertSame( $existing->get_data(), $unknown->get_data() );

		// A diferença fica só no envio do email.
		$this->assertSame( 1, $existing_ok );
		$this->assertSame( 1, $unknown_ok, 'Nenhum email deveria ter sido enviado para o endereço desconhecido.' );

		// E o código é criado nos dois casos (comportamento idêntico no storage).
		$this->assertIsArray( get_transient( $this->stored_code_option( self::EMAIL ) ) );
		$this->assertIsArray( get_transient( 'lkn_wcip_otp_' . md5( 'nao_existe@example.org' ) ) );
	}

	public function test_remaining_attempts_are_reported_before_the_lockout(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$wrong = '000000';

		for ( $attempt = 0; $attempt < 4; $attempt++ ) {
			$this->verify( self::EMAIL, $wrong );
		}

		// Ainda dentro do orçamento: o código correto continua valendo.
		$response = $this->verify( self::EMAIL, $this->code_from_last_mail() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_lockout_releases_after_the_window(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$this->send( self::EMAIL );
		$wrong = '000000';

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$this->verify( self::EMAIL, $wrong );
		}

		$this->assertSame( 429, $this->verify( self::EMAIL, $wrong )->get_status() );

		// Simula a passagem dos 15 minutos de bloqueio.
		delete_transient( 'lkn_wcip_otp_lockout_' . md5( self::EMAIL ) );
		delete_transient( 'lkn_wcip_otp_throttle_' . md5( self::EMAIL ) );

		$this->assertSame( 200, $this->send( self::EMAIL )->get_status() );

		$response = $this->verify( self::EMAIL, $this->code_from_last_mail() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( get_user_by( 'email', self::EMAIL )->ID, get_current_user_id() );
	}

	public function test_send_window_limit_is_enforced(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		for ( $i = 0; $i < 10; $i++ ) {
			delete_transient( 'lkn_wcip_otp_throttle_' . md5( self::EMAIL ) );

			$this->assertSame( 200, $this->send( self::EMAIL )->get_status(), 'Envio ' . ( $i + 1 ) );
		}

		delete_transient( 'lkn_wcip_otp_throttle_' . md5( self::EMAIL ) );

		// 11º envio dentro da janela de 1 hora.
		$this->assertSame( 429, $this->send( self::EMAIL )->get_status() );
		$this->assertCount( 10, $this->mails );
	}

	/* --------------------------------------------------------------------- */
	/* Registro (modo completo)                                              */
	/* --------------------------------------------------------------------- */

	public function test_full_mode_creates_a_customer_on_first_login(): void {
		$this->enable_otp( 'register_and_login' );
		$this->intercept_mail();

		$email = 'novo_cliente@example.org';

		$this->assertSame( 200, $this->send( $email )->get_status() );
		$code = $this->code_from_last_mail();

		$response = $this->verify( $email, $code );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );

		$user = get_user_by( 'email', $email );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertContains( 'customer', $user->roles );
		$this->assertSame( $user->ID, get_current_user_id() );
	}

	/* --------------------------------------------------------------------- */
	/* Migração do storage antigo                                            */
	/* --------------------------------------------------------------------- */

	public function test_legacy_plaintext_codes_are_purged_without_touching_settings(): void {
		$this->enable_otp();
		$this->user();
		$this->intercept_mail();

		$legacy_key = 'lkn_wcip_otp_' . md5( 'antigo@example.org' );
		update_option(
			$legacy_key,
			array(
				'code'       => '123456',
				'email'      => 'antigo@example.org',
				'expires_at' => time() + 600,
				'used'       => false,
			)
		);

		$this->send( self::EMAIL );

		$this->assertFalse( get_option( $legacy_key ) );
		$this->assertSame( 'login_only', get_option( 'lkn_wcip_otp_email_enable_type' ) );
		$this->assertSame( 10, (int) get_option( 'lkn_wcip_otp_email_expiration_time', 10 ) );
	}
}
