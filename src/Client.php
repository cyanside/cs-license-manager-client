<?php

namespace Cyanside\LicenseManagerClient;

use WP_Error;

class Client {

	/**
	 * Rest base for the remote client manager.
	 */
	const REST_BASE = 'cslm/v1';

	/**
	 * @var string
	 */
	protected $slug;

	/**
	 * @var string
	 */
	protected $hash;

	/**
	 * @var string
	 */
	protected $server_domain;

	/**
	 * @var bool
	 */
	protected $ssl_verify = true;

	/**
	 * @var License
	 */
	protected $license;

	/**
	 * @var string
	 */
	protected $file;

	/**
	 * Initializes the client.
	 *
	 * @return License
	 */
	public function init(): License {
		if ( ! empty( $this->license ) ) {
			return $this->license;
		}

		$this->license = new License( $this );

		return $this->license;
	}

	/**
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * @param string $slug
	 *
	 * @return Client
	 */
	public function set_slug( string $slug ): Client {
		$this->slug = $slug;

		return $this;
	}

	/**
	 * @return string
	 */
	public function get_file(): string {
		return $this->file;
	}

	/**
	 * @param string $file
	 *
	 * @return Client
	 */
	public function set_file( string $file ): Client {
		$this->file = $file;

		return $this;
	}

	/**
	 * Send request to remote endpoint.
	 *
	 * @param array $params
	 * @param string $route
	 *
	 * @return array|WP_Error The response or WP_Error on failure.
	 */
	public function send_request( array $params, string $route ) {
		$url = trailingslashit( $this->base_url() ) . untrailingslashit( $route );

		$headers = [
			'Accept' => 'application/json',
		];

		$args = [
			'method'    => 'POST',
			'headers'   => $headers,
			'timeout'   => 30,
			'body'      => array_merge(
				$params,
				[
					'domain' => esc_url( home_url() ),
					'hash'   => $this->get_hash(),
				]
			),
			'sslverify' => $this->is_ssl_verify(),
		];

		return wp_remote_post(
			$url,
			$args
		);
	}

	/**
	 * @return bool
	 */
	public function is_ssl_verify(): bool {
		return $this->ssl_verify;
	}

	/**
	 * @param bool $ssl_verify
	 *
	 * @return Client
	 */
	public function set_ssl_verify( bool $ssl_verify ): Client {
		$this->ssl_verify = $ssl_verify;

		return $this;
	}

	/**
	 * @return string
	 */
	public function get_server_domain(): string {
		return $this->server_domain;
	}

	/**
	 * @param string $server_domain
	 *
	 * @return Client
	 */
	public function set_server_domain( string $server_domain ): Client {
		$this->server_domain = $server_domain;

		return $this;
	}

	/**
	 * @return string
	 */
	public function get_hash(): string {
		return $this->hash;
	}

	/**
	 * @param string $hash
	 *
	 * @return Client
	 */
	public function set_hash( string $hash ): Client {
		$this->hash = $hash;

		return $this;
	}

	/**
	 * Gets base url for the server.
	 *
	 * @return string
	 */
	private function base_url(): string {
		$server_url = $this->get_server_url();
		$rest_base = self::REST_BASE;

		return "$server_url/wp-json/$rest_base";
	}

	/**
	 * Gets server url.
	 *
	 * @return string
	 */
	private function get_server_url(): string {
		$protocol = $this->is_ssl_verify() ? 'https' : 'http';

		return $protocol . '://' . $this->get_server_domain();
	}
}
