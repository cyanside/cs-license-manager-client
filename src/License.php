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
	 * Class constructor.
	 */
	public function __construct( Client $client ) {
		$this->client     = $client;
		$this->option_key = '_cs_license_manager_' . md5( $this->client->get_slug() );
	}

	/**
	 * Activates a license.
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public function activate( array $args ): void {
		$response = $this->client->send_request(
			$args,
			'licenses/activate'
		);

		if ( ! is_wp_error( $response ) ) {
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );
		} // TODO: handle 'else' condition for internal errors.
	}

	/**
	 * Deactivates a license.
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public function deactivate( array $args ): void {
		$response = $this->client->send_request(
			$args,
			'licenses/deactivate'
		);

		if ( ! is_wp_error( $response ) ) {
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );
		} // TODO: handle 'else' condition for internal errors.
	}

	/**
	 * Validates a license.
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public function validate( array $args ): void {
		$response = $this->client->send_request(
			$args,
			'licenses/validate'
		);

		if ( ! is_wp_error( $response ) ) {
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );
		} // TODO: handle 'else' condition for internal errors.
	}
}
