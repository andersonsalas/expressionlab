<?php
/**
 * This file is part of the Expression Lab plugin.
 *
 * (c) Anderson Salas <github@andersonsalas.com>
 *
 * See the LICENSE file for license information.
 *
 * @package ExpressionLab
 */

namespace ExpressionLab\Core\Services;

use ExpressionLab\Core\LanguageEngine;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Http Service.
 *
 * Provides a safe HTTP client wrapper over the WordPress HTTP API (`wp_remote_request()`).
 *
 * Supports `GET`, `POST`, and `HEAD` operations along with network latency benchmarking
 * and visual diagnostics reporting (`ping()`).
 *
 * @package ExpressionLab
 */
final class Http {
	/**
	 * Default request timeout in seconds.
	 */
	const DEFAULT_TIMEOUT = 10;

	/**
	 * Maximum response body length to capture (1 MB).
	 */
	const MAX_BODY_BYTES = 1048576;

	/**
	 * Maximum JSON decode nesting depth.
	 */
	const MAX_JSON_DEPTH = 64;

	/**
	 * Allowlisted user-overridable request options.
	 */
	const ALLOWED_OPTIONS = array(
		'timeout',
		'httpversion',
		'user-agent',
		'headers',
		'body',
		'cookies',
		'compress',
		'decompress',
		'blocking',
	);

	/**
	 * Magic method to expose constants as properties.
	 *
	 * @internal
	 *
	 * @param string $name Property name.
	 * @return mixed Constant value if defined, null otherwise.
	 */
	public function __get( string $name ) {
		if ( defined( "self::$name" ) ) {
			return constant( "self::$name" );
		}
		return null;
	}

	/**
	 * Ensures network operations are permitted.
	 *
	 * @internal
	 *
	 * @throws \RuntimeException If EXPRESSION_LAB_NETWORK_READONLY is true.
	 */
	private function check_permission() {
		if ( defined( 'EXPRESSION_LAB_NETWORK_READONLY' ) && EXPRESSION_LAB_NETWORK_READONLY ) {
			throw new \RuntimeException( 'Operation denied. Network operations are disabled. Set `EXPRESSION_LAB_NETWORK_READONLY` to `false` in `wp-config.php`.' );
		}
	}

	/**
	 * Filters request options against an allowlist of permitted keys.
	 *
	 * @internal
	 *
	 * @param array $options Raw user-supplied options.
	 * @return array Sanitized options containing only allowlisted keys.
	 */
	private function sanitize_options( array $options ): array {
		return array_intersect_key( $options, array_flip( self::ALLOWED_OPTIONS ) );
	}

	/**
	 * Validates that the URL uses an allowed scheme and does not target restricted hosts.
	 *
	 * @internal
	 *
	 * @param string $url Target URL.
	 * @throws \InvalidArgumentException If the URL is malformed, uses an invalid scheme, or resolves to a restricted IP address.
	 */
	private function validate_url( string $url ) {
		$this->check_permission();

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( 'Invalid URL provided: ' . esc_html( $url ) );
		}

		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid URL scheme. Only HTTP and HTTPS are permitted.' );
		}

		// Verifies hostname does not resolve to loopback/private/metadata ranges.
		$validated_url = wp_http_validate_url( $url );
		if ( false === $validated_url ) {
			throw new \InvalidArgumentException( 'The destination host is prohibited (private, loopback, or metadata address).' );
		}
	}

	/**
	 * Executes a WordPress HTTP request and formats the response structure.
	 *
	 * @internal
	 *
	 * @param string $method  HTTP method ('GET', 'POST', 'HEAD').
	 * @param string $url     Target URL.
	 * @param array  $options Request arguments.
	 * @return array The formatted response.
	 * @throws \RuntimeException If request encounters a transport error.
	 */
	private function send_request( string $method, string $url, array $options = array() ): array {
		$this->validate_url( $url );
		LanguageEngine::get()->tick();

		$start_time = microtime( true );

		// Filter user-supplied options against allowlisted keys before merging.
		$safe_options = $this->sanitize_options( $options );

		$args = array_merge(
			array(
				'method'              => $method,
				'timeout'             => self::DEFAULT_TIMEOUT,
				'redirection'         => 5,
				'httpversion'         => '1.1',
				'user-agent'          => 'ExpressionLab/' . EXPRESSION_LAB_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
				'reject_unsafe_urls'  => true,
				'sslverify'           => true,
				'headers'             => array(),
				'limit_response_size' => self::MAX_BODY_BYTES,
			),
			$safe_options
		);

		$raw_response = wp_remote_request( $url, $args );
		$duration_ms  = round( ( microtime( true ) - $start_time ) * 1000, 2 );
		LanguageEngine::get()->tick();

		if ( is_wp_error( $raw_response ) ) {
			throw new \RuntimeException( esc_html( 'HTTP Request failed: ' . $raw_response->get_error_message() ) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $raw_response );
		$status_msg  = (string) wp_remote_retrieve_response_message( $raw_response );
		$headers_raw = wp_remote_retrieve_headers( $raw_response );
		$headers     = is_object( $headers_raw ) && method_exists( $headers_raw, 'getAll' ) ? $headers_raw->getAll() : (array) $headers_raw;
		$body        = (string) wp_remote_retrieve_body( $raw_response );

		// Defense-in-depth: cap body size even though limit_response_size should already enforce it.
		if ( strlen( $body ) > self::MAX_BODY_BYTES ) {
			$body = substr( $body, 0, self::MAX_BODY_BYTES );
		}

		// Attempt JSON parsing with depth limit.
		$json = null;
		if ( ! empty( $body ) ) {
			$decoded = json_decode( $body, true, self::MAX_JSON_DEPTH );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$json = $decoded;
			}
		}

		return array(
			'status'       => $status_code,
			'status_text'  => $status_msg,
			'headers'      => $headers,
			'body'         => $body,
			'json'         => $json,
			'duration_ms'  => $duration_ms,
			'content_type' => isset( $headers['content-type'] ) ? (string) $headers['content-type'] : '',
		);
	}

	/**
	 * Perform an HTTP GET request to the specified target URL.
	 *
	 * Sends an outbound GET request via the WordPress HTTP API (`wp_remote_request()`).
	 * Automatically validates the destination and sanitizes `options` against `Http.ALLOWED_OPTIONS`.
	 *
	 * Return structure:
	 * * `status` (`int`): HTTP response status code (e.g. `200`, `404`, `500`).
	 * * `status_text` (`string`): HTTP status reason phrase (e.g. `'OK'`, `'Not Found'`).
	 * * `headers` (`array`): Associative array of response headers returned by the server.
	 * * `body` (`string`): Raw response body string, truncated to 1 MB (`Http.MAX_BODY_BYTES`).
	 * * `json` (`mixed|null`): Parsed JSON payload if valid and within 64 depth levels (`Http.MAX_JSON_DEPTH`); `null` otherwise.
	 * * `duration_ms` (`float`): Roundtrip request latency in milliseconds.
	 * * `content_type` (`string`): Response `Content-Type` header value.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Http.get('https://api.github.com/zen')
	 * ```
	 *
	 * ```elscript
	 * Http.get('https://api.github.com/zen')['status']
	 * ```
	 *
	 * ```elscript
	 * Http.get('https://api.github.com/users/octocat')['json']
	 * ```
	 *
	 * ```elscript
	 * Http.get(
	 *      'https://httpbin.org/headers',
	 *      {
	 *          'Accept': 'application/json',
	 *          'Authorization': 'Bearer sample-token-abc'
	 *      }
	 * )
	 * ```
	 *
	 * ```elscript
	 * Http.get(
	 *      'https://httpbin.org/delay/2',
	 *      [],
	 *      {
	 *          'timeout': 20,
	 *          'user-agent': 'MyCustomApp/2.0'
	 *      }
	 * )
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/http#httpget
	 *
	 * @param string $url     Target HTTP or HTTPS URL.
	 * @param array  $headers Optional associative array of custom request headers. Default empty array.
	 * @param array  $options Optional transport arguments from allowlisted keys (`timeout`, `httpversion`, `user-agent`, `headers`, `body`, `cookies`, `compress`, `decompress`, `blocking`). Default empty array.
	 * @return array Normalized response associative array.
	 * @throws \RuntimeException If network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY` or transport encounters an error.
	 * @throws \InvalidArgumentException If the URL is malformed, uses an unpermitted scheme, or targets a prohibited host.
	 */
	public function get( string $url, array $headers = array(), array $options = array() ): array {
		if ( ! empty( $headers ) ) {
			$options['headers'] = array_merge( $options['headers'] ?? array(), $headers );
		}
		return $this->send_request( 'GET', $url, $options );
	}

	/**
	 * Perform an HTTP POST request with an optional payload to the specified target URL.
	 *
	 * Sends an outbound POST request via the WordPress HTTP API.
	 * Payload serialization behavior:
	 * * **Array with JSON Content-Type**: If `body` is an array and `headers` includes a `Content-Type` containing `'json'` (case-insensitive), it is automatically serialized with `wp_json_encode()`.
	 * * **Array without JSON Content-Type**: Formatted as form data (`application/x-www-form-urlencoded` or multipart) by the underlying HTTP transport.
	 * * **Scalar / String**: Cast to string and sent directly as the request body.
	 * * **Null**: No request body is transmitted.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Http.post(
	 *      'https://httpbin.org/post',
	 *      {
	 *          'action': 'sync',
	 *          'site_id': 1
	 *      },
	 *      { 'Content-Type': 'application/json' }
	 * )
	 * ```
	 *
	 * ```elscript
	 * Http.post(
	 *      'https://httpbin.org/post',
	 *      {
	 *          'username': 'administrator',
	 *          'grant_type': 'password'
	 *      }
	 * )
	 * ```
	 *
	 * ```elscript
	 * Http.post(
	 *      'https://httpbin.org/post',
	 *      'raw-payload-string',
	 *      { 'Content-Type': 'text/plain' }
	 * )
	 * ```
	 *
	 * ```elscript
	 * Http.post(
	 *      'https://httpbin.org/post',
	 *      { 'event': 'ping' },
	 *      { 'Content-Type': 'application/json' }
	 * )['json']
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/http#httppost
	 *
	 * @param string $url     Target HTTP or HTTPS URL.
	 * @param mixed  $body    Optional request payload (array, string, or null). Default null.
	 * @param array  $headers Optional associative array of custom request headers. Default empty array.
	 * @param array  $options Optional transport arguments from allowlisted keys. Default empty array.
	 * @return array Normalized response associative array.
	 * @throws \RuntimeException If network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY` or transport encounters an error.
	 * @throws \InvalidArgumentException If the URL is malformed, uses an unpermitted scheme, or targets a prohibited host.
	 */
	public function post( string $url, $body = null, array $headers = array(), array $options = array() ): array {
		$options['headers'] = array_merge( $options['headers'] ?? array(), $headers );

		if ( is_array( $body ) ) {
			$headers_lower   = array_change_key_case( $options['headers'] ?? array(), CASE_LOWER );
			$is_json         = isset( $headers_lower['content-type'] ) && str_contains( strtolower( (string) $headers_lower['content-type'] ), 'json' );
			$options['body'] = $is_json ? wp_json_encode( $body ) : $body;
		} elseif ( null !== $body ) {
			$options['body'] = (string) $body;
		}

		return $this->send_request( 'POST', $url, $options );
	}

	/**
	 * Perform an HTTP HEAD request to retrieve response metadata and headers.
	 *
	 * Retrieves response status and server headers without transferring the response body.
	 * The returned structure contains an empty string `body` and `null` `json`, while populating
	 * `status`, `status_text`, `headers`, `duration_ms`, and `content_type`.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Http.head('https://one.one.one.one')
	 * ```
	 *
	 * ```elscript
	 * Http.head('https://one.one.one.one')['headers']
	 * ```
	 *
	 * ```elscript
	 * Http.head('https://one.one.one.one')['content_type']
	 * ```
	 *
	 * ```elscript
	 * Http.head('https://one.one.one.one')['headers']['server']
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/http#httphead
	 *
	 * @param string $url     Target HTTP or HTTPS URL.
	 * @param array  $headers Optional associative array of custom request headers. Default empty array.
	 * @param array  $options Optional transport arguments from allowlisted keys. Default empty array.
	 * @return array Normalized response associative array with empty body and headers populated.
	 * @throws \RuntimeException If network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY` or transport encounters an error.
	 * @throws \InvalidArgumentException If the URL is malformed, uses an unpermitted scheme, or targets a prohibited host.
	 */
	public function head( string $url, array $headers = array(), array $options = array() ): array {
		if ( ! empty( $headers ) ) {
			$options['headers'] = array_merge( $options['headers'] ?? array(), $headers );
		}
		return $this->send_request( 'HEAD', $url, $options );
	}

	/**
	 * Ping a URL, benchmark roundtrip latency, and generate a visual diagnostics table.
	 *
	 * Performs a diagnostic GET request to the target URL with a 5-second timeout,
	 * measures response time, checks endpoint availability, and renders an interactive
	 * `Table: HTTP Ping Diagnostics` card in the Expression Lab console.
	 *
	 * Metrics rendered in the diagnostic table:
	 * * `Target URL`: Evaluated destination endpoint.
	 * * `HTTP Status`: Combined status code and message (e.g. `200 OK`).
	 * * `Latency (ms)`: Total roundtrip duration formatted in milliseconds.
	 * * `Content Type`: MIME type reported by the remote server.
	 * * `Payload Size`: Response body byte length.
	 *
	 * Examples:
	 *
	 * ```elscript
	 * Http.ping('https://one.one.one.one')
	 * ```
	 *
	 * ```elscript
	 * Http.ping('https://one.one.one.one')['is_healthy']
	 * ```
	 *
	 * ```elscript
	 * Http.ping('https://one.one.one.one')['latency_ms']
	 * ```
	 *
	 * @see https://expressionlab.io/docs/api-reference/http#httpping
	 *
	 * @param string $url Target HTTP or HTTPS URL to benchmark.
	 * @return array Diagnostic summary containing 'url', 'status', 'latency_ms', and 'is_healthy'.
	 * @throws \RuntimeException If network operations are disabled via `EXPRESSION_LAB_NETWORK_READONLY` or transport encounters an error.
	 * @throws \InvalidArgumentException If the URL is malformed, uses an unpermitted scheme, or targets a prohibited host.
	 */
	public function ping( string $url ): array {
		$response = $this->send_request( 'GET', $url, array( 'timeout' => 5 ) );

		$metrics = array(
			array(
				'metric' => 'Target URL',
				'value'  => $url,
			),
			array(
				'metric' => 'HTTP Status',
				'value'  => (string) $response['status'] . ' ' . $response['status_text'],
			),
			array(
				'metric' => 'Latency (ms)',
				'value'  => (string) $response['duration_ms'] . ' ms',
			),
			array(
				'metric' => 'Content Type',
				'value'  => (string) $response['content_type'],
			),
			array(
				'metric' => 'Payload Size',
				'value'  => strlen( (string) $response['body'] ) . ' bytes',
			),
		);

		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'table',
				'title' => 'Table: HTTP Ping Diagnostics',
				'data'  => $metrics,
			)
		);

		return array(
			'url'        => $url,
			'status'     => $response['status'],
			'latency_ms' => $response['duration_ms'],
			'is_healthy' => $response['status'] >= 200 && $response['status'] < 400,
		);
	}
}
