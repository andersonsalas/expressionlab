<?php // phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase
// phpcs:disable Squiz.Commenting.FileComment.Missing

declare(strict_types=1);

/**
 * Finder instance.
 *
 * @var Symfony\Component\Finder\Finder $finder
 */
$finder = Isolated\Symfony\Component\Finder\Finder::class;

$excluded_files = array();

return array(
	'prefix'                  => 'ExpressionLab\\Vendor',
	'output-dir'              => 'build/scoped',
	'finders'                 => array(

		$finder::create()
			->files()
			->ignoreVCS( true )
			->notName( '/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/' )
			->exclude(
				array(
					'doc',
					'test',
					'test_old',
					'tests',
					'Tests',
					'vendor-bin',
				)
			)
			->in( 'vendor' ),

		$finder::create()
			->files()
			->depth( 0 )
			->name( 'composer.json' )
			->in( '.' ),

	),
	'exclude-files'           => array(
		...$excluded_files,
	),
	'php-version'             => null,
	'patchers'                => array(
		static function ( string $file_path, string $prefix, string $contents ): string {
			return $contents;
		},
	),
	'exclude-namespaces'      => array(
		'ExpressionLab',
	),
	'exclude-classes'         => array(
		'WP_*',
		'wpdb',
	),
	'exclude-functions'       => array(
		'add_action',
		'add_filter',
		'do_action',
		'apply_filters',
		'plugin_dir_path',
		'plugin_dir_url',
	),
	'exclude-constants'       => array(
		'ABSPATH',
		'/^EXPRESSION_LAB_/',
	),
	'expose-global-constants' => true,
	'expose-global-classes'   => true,
	'expose-global-functions' => true,
	'expose-namespaces'       => array(),
	'expose-classes'          => array(),
	'expose-functions'        => array(),
	'expose-constants'        => array(),
);
