<?php

namespace Cyanside\LicenseManagerClient;

class License {

	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @var string
	 */
	protected $option_key;

	/**
	 * @var array
	 */
	protected $response;

	/**
	 * @var string
	 */
	protected $message;

	/**
	 * @var string
	 */
	protected $cron_event_hook;

	/**
	 * Class constructor.
	 */
	public function __construct( Client $client ) {
		$this->client          = $client;
		$this->option_key      = '_cs_license_manager_' . md5( $this->client->get_slug() );
		$this->cron_event_hook = '_cs_license_manager_event_' . $this->client->get_slug();

		add_action( 'wp_ajax_cslm_license_' . $this->client->get_slug(), [ $this, 'ajax_handler' ] );
		add_action( 'admin_menu', [ $this, 'register_sub_menu' ], 99 );

		// Cron event to refresh license data daily.
		add_action( $this->cron_event_hook, [ $this, 'refresh_license_data' ] );

		$this->init_schedule();
	}

	/**
	 * Initializes cron events on lifecycle hooks.
	 *
	 * @return void
	 */
	private function init_schedule() {
		register_activation_hook( $this->client->get_file(), [ $this, 'schedule_cron_event' ] );
		register_deactivation_hook( $this->client->get_file(), [ $this, 'clear_scheduler' ] );
	}

	/**
	 * Gets the saved license.
	 *
	 * @return array|null
	 */
	public function get_license(): ?array {
		return get_option( $this->option_key, null );
	}

	/**
	 * Registers submenu page for license manager.
	 *
	 * @return void
	 */
	public function register_sub_menu(): void {
		add_submenu_page(
			$this->client->get_slug(),
			'Manage License',
			'Manage License',
			'manage_options',
			$this->client->get_slug() . '-license',
			[ $this, 'submenu_render_callback' ]
		);
	}

	/**
	 * Renders the submenu page.
	 *
	 * @return void
	 */
	public function submenu_render_callback() {
		$this->handle_submit();

		$is_error = true;

		if ( ! empty( $this->response ) && is_array( $this->response ) ) {
			$this->message = $this->response['message'];
			if ( $this->client->is_success_response( $this->response ) ) {
				$is_error = false;
			}
		}

		$license = $this->get_license();

		$license_key = $license['license_key'] ?? '';
		$email       = $license['email'] ?? '';

		$is_license_active = ( $license && isset( $license['status'] ) && 'active' === $license['status'] );
		$action            = $is_license_active ? 'deactivate' : 'activate';

		$license_status_text = 'Activated';

		if ( ! $is_license_active && ! empty( $license['status'] ) ) {
			if ( $license['status'] === 'inactive' ) {
				$license_status_text = 'Not Activated';
			} elseif ( $license['status'] === 'expired' ) {
				$license_status_text = 'Expired';
			}
		}
		?>
		<style>
			.cs-license-manager-wrapper {
				width: 380px;
				padding: 8% 0 0;
				margin: auto;
			}
			.cs-license-manager-wrapper .form-wrapper {
				position: relative;
				z-index: 1;
				background: #FFFFFF;
				max-width: 360px;
				margin: 0 auto 100px;
				padding: 22px;
				box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
				border-radius: 16px;
			}
			.cs-license-manager-wrapper .form-wrapper .is-error {
				color: #dc2626;
			}
			.cs-license-manager-wrapper .form-wrapper .active {
				color: #22c55e;
			}
			.cs-license-manager-wrapper .license-form input {
				font-family: system-ui;
				outline: 0;
				background: #f2f2f2;
				width: 100%;
				border: 0;
				margin: 0 0 15px;
				padding: 15px;
				box-sizing: border-box;
				font-size: 14px;
				border-radius: 8px;
			}
			.cs-license-manager-wrapper .license-form button {
				font-family: system-ui;
				text-transform: uppercase;
				outline: 0;
				background: #f97316;
				width: 100%;
				border: 0;
				padding: 15px;
				color: #FFFFFF;
				font-size: 14px;
				cursor: pointer;
				border-radius: 8px;
				font-weight: bold;
			}
			.cs-license-manager-wrapper .license-form button:hover,.form button:active,.form button:focus {
				background: #ea580c;
			}
			.cs-license-manager-wrapper .license-form .secondary {
				font-family: system-ui;
				text-transform: uppercase;
				outline: 0;
				border: 1px solid #f97316;
				background: #FFFFFF;
				width: 100%;
				padding: 15px;
				color: #f97316;
				font-size: 14px;
				cursor: pointer;
				border-radius: 8px;
				font-weight: bold;
			}
			.cs-license-manager-wrapper .license-form .secondary:hover {
				background: #ea580c;
				color: white;
			}
		</style>

		<div class="cs-license-manager-wrapper">
			<div class="form-wrapper">
				<h1>Manage License</h1>
				<h2>License Status: <span class="<?php echo esc_attr( $is_license_active ? 'active' : '' ); ?> <?php echo esc_attr( $is_error ? 'is-error' : '' ); ?>"><?php echo esc_html( $license_status_text ); ?></span></h2>
				<p class="<?php echo esc_attr( $is_error ? 'is-error' : '' ); ?>"><?php echo esc_html( $this->message ); ?></p>
				<hr>
				<br>

				<form method="post" novalidate="novalidate" spellcheck="false" autocomplete="on" class="license-form">
					<input type="hidden" name="_action" value="<?php echo $action; ?>">
					<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( $this->client->get_slug() ); ?>">

					<label for="license-key">License Key*</label>
					<input id="license-key" name="license_key" <?php echo esc_attr( $is_license_active ? 'readonly' : '' ); ?> value="<?php echo esc_html( $license_key ?? '' ); ?>" type="text" placeholder="LNXXXX-XXXX-XXXX-XXXX-XXXX"/>
					<label for="email">Email*</label>
					<input id="email" name="email" <?php echo esc_attr( $is_license_active ? 'readonly' : '' ); ?> type="text" value="<?php echo esc_html( $email ?? '' ); ?>" placeholder="john@test.com"/>
					<button type="submit"><?php echo esc_html( $is_license_active ? 'Deactivate' : 'Activate' ); ?></button>
				</form>

				<?php if ( $license && $license['license_key'] ) { ?>
					<br>
					<form method="post" class="license-form" novalidate="novalidate" spellcheck="false">
						<input type="hidden" name="_action" value="validate">
						<input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( $this->client->get_slug() ); ?>">
						<input type="hidden" name="license_key" value="<?php echo esc_html( $license_key ?? '' ); ?>">
						<input type="hidden" name="email" value="<?php echo esc_html( $email ?? '' ); ?>">
						<button class="secondary" type="submit" name="submit">
							<span class="dashicons dashicons-update"></span>
							Refresh License
						</button>
					</form>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Submit handler for license management.
	 *
	 * @return void
	 */
	public function handle_submit(): void {
		if ( ! isset( $_POST['_action'] ) ) {
			return;
		}

		if ( ! isset( $_POST['_nonce'] )
		    || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_nonce'] ) ), $this->client->get_slug() ) ) {
			$this->message = 'Error! Server validation failed.';
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->message = 'Error! You do not have permission to do that.';
			return;
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$email       = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';
		$action      = sanitize_text_field( wp_unslash( $_POST['_action'] ) );

		if ( empty( $license_key ) || empty( $email ) || empty( $action ) ) {
			$this->message = 'Error! License key / Email can not be empty.';
			return;
		}

		if ( ! is_email( $email ) ) {
			$this->message = 'Error! Email is invalid.';
			return;
		}

		$params = [
			'license_key' => $license_key,
			'email'       => $email,
		];

		switch ( $action ) {
			case 'activate':
				$this->activate( $params );
				break;

			case 'deactivate':
				$this->deactivate( $params );
				break;

			case 'validate':
				$this->validate( $params );
				break;
		}
	}

	/**
	 * Activates a license.
	 *
	 * @param array $args
	 *
	 * @return array|mixed
	 */
	public function activate( array $args ) {
		$response = $this->client->send_request(
			$args,
			'licenses/activate'
		);

		if ( ! is_wp_error( $response ) ) {
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $this->client->is_success_response( $this->response ) ) {
				$data = [
					'status'      => 'active',
					'license_key' => $args['license_key'],
					'email'       => $args['email'],
				];

				update_option( $this->option_key, $data, false );
			} else {
				if ( empty( $this->response ) ) {
					$this->response = [
						'code' => '',
						'message' => 'Something went wrong. Please contact support.',
						'data' => [
							'status' => 400,
						],
					];
				}
			}
		} // TODO: handle 'else' condition for internal errors.

		return $this->response;
	}

	/**
	 * Deactivates a license.
	 *
	 * @param array $args
	 *
	 * @return array|mixed
	 */
	public function deactivate( array $args ) {
		$response = $this->client->send_request(
			$args,
			'licenses/deactivate'
		);

		if ( ! is_wp_error( $response ) ) {
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $this->client->is_success_response( $this->response ) ) {
				$data = [
					'status'      => 'inactive',
					'license_key' => '',
					'email'       => '',
				];

				update_option( $this->option_key, $data, false );
			} else {
				if ( empty( $this->response ) ) {
					$this->response = [
						'code' => '',
						'message' => 'Something went wrong. Please contact support.',
						'data' => [
							'status' => 400,
						],
					];
				}
			}
		}

		return $this->response;
	}

	/**
	 * Validates a license.
	 *
	 * @param array $args
	 *
	 * @return array|mixed
	 */
	public function validate( array $args ) {
		$response = $this->client->send_request(
			$args,
			'licenses/validate'
		);

		if ( ! is_wp_error( $response ) ) {
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $this->client->is_success_response( $this->response ) ) {
				$data = [
					'status'      => 'active',
					'license_key' => $args['license_key'],
					'email'       => $args['email'],
				];
			} else {
				$data = array_merge(
					$args,
					[
						'status' => 'expired',
					],
				);
			}
			update_option( $this->option_key, $data, false );
		}

		return $this->response;
	}

	/**
	 * Checks if the current response is a successful one.
	 *
	 * @return bool
	 */
	public function is_success_response(): bool {
		return isset( $this->response['data']['status'] ) && $this->response['data']['status'] === 200;
	}

	/**
	 * Schedule License checker event.
	 *
	 * @return void
	 */
	public function schedule_cron_event(): void {
		if ( ! wp_next_scheduled( $this->cron_event_hook ) ) {
			wp_schedule_event( time(), 'daily', $this->cron_event_hook );

			wp_schedule_single_event( time() + 10, $this->cron_event_hook );
		}
	}

	/**
	 * Clear any previously scheduled hook.
	 *
	 * @return void
	 */
	public function clear_scheduler(): void {
		wp_clear_scheduled_hook( $this->cron_event_hook );
	}

	/**
	 * Refreshes the license.
	 *
	 * @return void
	 */
	public function refresh_license_data(): void {
		$license = $this->get_license();

		if ( ! $license || empty( $license['license_key'] || empty( $license['email'] ) ) ) {
			return;
		}

		$params = [
			'license_key' => $license['license_key'],
			'email'       => $license['email'],
		];

		$this->validate( $params );
	}
}
