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

namespace ExpressionLab\Core\Cli\Commands;

use ExpressionLab\Core\Cli\Runners\IPLookupRunner;
use ExpressionLab\Core\Services\IPLookup;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Manages the MaxMind GeoLite2 database for the IPLookup service.
 *
 * MaxMind and GeoLite2 are registered trademarks of MaxMind, Inc.
 *
 * @package ExpressionLab
 */
class IPLookupCommand {

	/**
	 * Runner instance.
	 *
	 * @var IPLookupRunner
	 */
	private $runner;

	/**
	 * IPLookupCommand constructor.
	 *
	 * @param IPLookupRunner|null $runner Optional runner instance.
	 */
	public function __construct( ?IPLookupRunner $runner = null ) {
		$this->runner = $runner ?? new IPLookupRunner();
	}

	/**
	 * Downloads or updates the local MaxMind GeoLite2 Country database.
	 *
	 * ## OPTIONS
	 *
	 * [--license-key=<key>]
	 * : MaxMind license key. If omitted, falls back to the EXPRESSION_LAB_MAXMIND_API_KEY constant.
	 *
	 * [--force]
	 * : Force download and update even if the database was updated recently.
	 *
	 * ## EXAMPLES
	 *
	 *     wp expressionlab iplookup update
	 *     wp expressionlab iplookup update --license-key=my_license_key
	 *     wp expressionlab iplookup update --force
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function update( array $args, array $assoc_args ): void {
		unset( $args );

		$license_key = isset( $assoc_args['license-key'] ) ? $assoc_args['license-key'] : null;
		$force       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		try {
			$result = $this->runner->update(
				$license_key,
				$force,
				function ( string $message ) {
					\WP_CLI::log( $message );
				}
			);

			if ( 'skipped' === $result['status'] ) {
				\WP_CLI::log( 'Database was updated in the last 24 hours. Use --force to update anyway.' );
				return;
			}

			\WP_CLI::success(
				sprintf(
					'GeoLite2 Country database updated successfully (%s, build: %s). Path: %s',
					$result['size_formatted'],
					$result['build_date'],
					$result['path']
				)
			);
		} catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Deletes the local MaxMind GeoLite2 Country database.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip interactive confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp expressionlab iplookup delete
	 *     wp expressionlab iplookup delete --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( array $args, array $assoc_args ): void {
		unset( $args );

		$target_path = IPLookup::get_database_path();

		if ( ! file_exists( $target_path ) ) {
			\WP_CLI::warning( sprintf( 'No database found at target path: %s', $target_path ) );
			return;
		}

		\WP_CLI::confirm( 'Are you sure you want to delete the GeoLite2 database?', $assoc_args );

		try {
			$this->runner->delete();
			\WP_CLI::success( 'Database deleted successfully.' );
		} catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Displays the status and metadata of the local MaxMind GeoLite2 database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp expressionlab iplookup status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$status = $this->runner->get_status();

		$rows = array(
			array(
				'Field' => 'Database Status',
				'Value' => $status['installed'] ? 'Installed' : 'Not Installed',
			),
			array(
				'Field' => 'File Path',
				'Value' => $status['path'],
			),
			array(
				'Field' => 'Path Type',
				'Value' => $status['path_type'],
			),
			array(
				'Field' => 'License Key Status',
				'Value' => $status['license_configured'] ? 'Configured in wp-config.php' : 'Not configured',
			),
		);

		if ( $status['installed'] ) {
			$rows[] = array(
				'Field' => 'File Size',
				'Value' => $status['size_formatted'],
			);
			$rows[] = array(
				'Field' => 'Last Modified',
				'Value' => $status['modified'],
			);

			if ( ! empty( $status['database_type'] ) ) {
				$rows[] = array(
					'Field' => 'Database Type',
					'Value' => $status['database_type'],
				);
			}
			if ( ! empty( $status['ip_version'] ) ) {
				$rows[] = array(
					'Field' => 'IP Version',
					'Value' => sprintf( 'IPv%d', $status['ip_version'] ),
				);
			}
			if ( ! empty( $status['build_date'] ) ) {
				$rows[] = array(
					'Field' => 'Build Epoch',
					'Value' => $status['build_date'],
				);
			}
			if ( ! empty( $status['reader_error'] ) ) {
				$rows[] = array(
					'Field' => 'Reader Error',
					'Value' => $status['reader_error'],
				);
			}
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'Field', 'Value' ) );

		if ( $status['is_stale'] ) {
			\WP_CLI::warning(
				sprintf(
					'The database was built %d days ago. MaxMind policies require updating GeoLite2 databases within 30 days.',
					$status['age_days']
				)
			);
		}
	}
}
