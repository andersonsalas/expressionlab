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

namespace ExpressionLab\Core\Models;

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Options;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Site options model class.
 *
 * Extension of the Options service scoped to a specific site in a multisite network.
 * Switches execution context to target the correct site's options table.
 *
 * @since 1.0.0
 * @package ExpressionLab
 */
final class SiteOptions extends Options {
	/**
	 * WordPress site instance.
	 *
	 * @since 1.0.0
	 * @internal
	 * @var \WP_Site|null
	 */
	private $site;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param \WP_Site|null $site The WP_Site object to scope options to.
	 */
	public function __construct( ?\WP_Site $site ) {
		parent::__construct();
		$this->site = $site;
	}

	/**
	 * Validates that the site object is present and saved.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return int The validated blog ID.
	 * @throws \Exception If the site instance is unsaved or null.
	 */
	private function ensure_valid_site(): int {
		if ( null === $this->site || empty( $this->site->blog_id ) ) {
			throw new \Exception( esc_html( 'Cannot access site options on an unsaved site instance. Call `save()` first.' ) );
		}
		return (int) $this->site->blog_id;
	}

	/**
	 * Retrieves a raw option value scoped to the current site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           The option key.
	 * @param mixed  $default_value The default value if the option is not set.
	 * @return mixed The option value or default.
	 */
	public function get_raw( string $key, $default_value = null ) {
		$target_blog_id  = $this->ensure_valid_site();
		$current_site_id = LanguageEngine::get()->get_site_id();
		LanguageEngine::get()->set_site_id( $target_blog_id );

		try {
			return parent::get_raw( $key, $default_value );
		} finally {
			LanguageEngine::get()->set_site_id( $current_site_id );
		}
	}

	/**
	 * Retrieves an option value scoped to the current site, attempting unserialization.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           The option key.
	 * @param mixed  $default_value The default value if the option is not set.
	 * @return mixed The processed option value.
	 */
	public function get( string $key, $default_value = null ) {
		$target_blog_id  = $this->ensure_valid_site();
		$current_site_id = LanguageEngine::get()->get_site_id();
		LanguageEngine::get()->set_site_id( $target_blog_id );

		try {
			return parent::get( $key, $default_value );
		} finally {
			LanguageEngine::get()->set_site_id( $current_site_id );
		}
	}

	/**
	 * Updates a raw option value scoped to the current site.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $key      The option key.
	 * @param string      $value    The raw string value to store.
	 * @param string|bool $autoload Whether to autoload. 'yes', 'no' or null (keep existing).
	 * @return bool True on success, false on failure.
	 */
	public function update_raw( string $key, string $value, $autoload = null ): bool {
		$target_blog_id  = $this->ensure_valid_site();
		$current_site_id = LanguageEngine::get()->get_site_id();
		LanguageEngine::get()->set_site_id( $target_blog_id );

		try {
			return parent::update_raw( $key, $value, $autoload );
		} finally {
			LanguageEngine::get()->set_site_id( $current_site_id );
		}
	}

	/**
	 * Updates an option value scoped to the current site with serialization.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key                The option key.
	 * @param mixed  $value              The option value.
	 * @param string $serialization_type Serialization format type.
	 * @return bool True on success, false on failure.
	 */
	public function update( string $key, $value, string $serialization_type = self::FORMAT_SERIALIZED ): bool {
		$target_blog_id  = $this->ensure_valid_site();
		$current_site_id = LanguageEngine::get()->get_site_id();
		LanguageEngine::get()->set_site_id( $target_blog_id );

		try {
			return parent::update( $key, $value, $serialization_type );
		} finally {
			LanguageEngine::get()->set_site_id( $current_site_id );
		}
	}

	/**
	 * Deletes an option scoped to the current site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The option key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key ): bool {
		$target_blog_id  = $this->ensure_valid_site();
		$current_site_id = LanguageEngine::get()->get_site_id();
		LanguageEngine::get()->set_site_id( $target_blog_id );

		try {
			return parent::delete( $key );
		} finally {
			LanguageEngine::get()->set_site_id( $current_site_id );
		}
	}

	/**
	 * Generates statistics for the site options table.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $sample_limit The maximum number of options to sample. Default 10000.
	 * @param int      $graph_limit  The maximum number of prefixes in the graph. Default 20.
	 * @return array Options statistics.
	 */
	public function stats( ?int $sample_limit = 10000, int $graph_limit = 20 ): array {
		$target_blog_id  = $this->ensure_valid_site();
		$current_site_id = LanguageEngine::get()->get_site_id();
		LanguageEngine::get()->set_site_id( $target_blog_id );

		try {
			return parent::stats( $sample_limit, $graph_limit );
		} finally {
			LanguageEngine::get()->set_site_id( $current_site_id );
		}
	}
}
