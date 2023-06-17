<?php

namespace Cyanside\LicenseManagerClient;

class Updater {

	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @var string
	 */
	protected $version;

	/**
	 * @var array
	 */
	protected $response;

	/**
	 * Class constructor.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
		$this->set_current_version();

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_plugin_update' ] );
	}

	/**
	 * Sets current version.
	 *
	 * @return void
	 */
	protected function set_current_version(): void {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( $this->client->get_file() );
		$this->version = $plugin_data['Version'];
	}

	/**
	 * Checks plugin updates.
	 *
	 * @param $transient
	 *
	 * @return mixed|\stdClass
	 */
	public function check_plugin_update( $transient ) {
		global $pagenow;

		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		if ( 'plugins.php' === $pagenow && is_multisite() ) {
			return $transient;
		}

		if ( ! empty( $transient->response ) && ! empty( $transient->response[ $this->client->get_base_name() ] ) ) {
			return $transient;
		}

		$license = $this->client->license_manager()->get_license();

		if ( empty( $license ) || empty( $license['license_key'] ) ) {
			return $transient;
		}

		if ( empty( $license['status'] ) || $license['status'] !== 'active' ) {
			return $transient;
		}

		$update_info = $this->get_update_info( $license );

		if ( false !== $update_info && is_object( $update_info ) && isset( $update_info->new_version ) ) {
			if ( version_compare( $this->version, $update_info->new_version, '<' ) ) {
				// Update is available.
				$transient->response[ $this->client->get_base_name() ] = $update_info;
			} else {
				// No update is available.
				$transient->no_update[ $this->client->get_base_name() ] = $update_info;
			}

			$transient->last_checked                              = time();
			$transient->checked[ $this->client->get_base_name() ] = $this->version;
		}

		return $transient;
	}

	/**
	 * Gets update info.
	 *
	 * @param $license
	 *
	 * @return object|null
	 */
	private function get_update_info( $license ): ?object {
		$params = [
			'license_key' => $license['license_key'],
			'email'       => $license['email'],
		];

		$response = $this->client->send_request( $params, 'updates/check' );

		if ( ! is_wp_error( $response ) ) {
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $this->client->is_success_response( $this->response ) ) {
				return null;
			}

			if ( ! empty( $this->response['data']['update_info']['new_version'] ) ) {
				return (object) array_merge(
					[
						'slug'   => $this->client->get_slug(),
						'id'     => $this->client->get_base_name(),
						'plugin' => $this->client->get_base_name(),
					],
					$this->response['data']['update_info']
				);
			}
		}

		return null;
	}
}
