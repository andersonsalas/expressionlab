<?php

// phpcs:ignoreFile

use ExpressionLab\Admin\Pages\Console;

/**
 * @group ajax
 */
class ConsoleFilesystemTest extends WP_Ajax_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Fixture directories to remove on tear down.
	 *
	 * @var string[]
	 */
	private $fixtures = array();

	public function setUp(): void {
		parent::setUp();

		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

		$admin_user_id  = defined( 'EXPRESSION_LAB_ADMIN_USER_ID' ) ? (int) constant( 'EXPRESSION_LAB_ADMIN_USER_ID' ) : 1;
		$existing_admin = get_user_by( 'id', $admin_user_id );
		if ( $existing_admin ) {
			$existing_admin->set_role( 'administrator' );
			$this->admin_id = $admin_user_id;
		} else {
			$this->admin_id = self::factory()->user->create( array(
				'role'      => 'administrator',
				'import_id' => $admin_user_id,
			) );
		}
		wp_set_current_user( $this->admin_id );

		Console::get()->init_hooks();
	}

	public function tearDown(): void {
		foreach ( $this->fixtures as $dir ) {
			$this->remove_tree( $dir );
		}
		wp_set_current_user( 0 );
		$_POST = array();
		$_GET  = array();
		unset( $_SERVER['REQUEST_METHOD'] );
		parent::tearDown();
	}

	public static function tear_down_after_class() {
		if ( ! has_action( 'admin_init', '_maybe_update_core' ) ) {
			add_action( 'admin_init', '_maybe_update_core' );
		}
		if ( ! has_action( 'admin_init', '_maybe_update_plugins' ) ) {
			add_action( 'admin_init', '_maybe_update_plugins' );
		}
		if ( ! has_action( 'admin_init', '_maybe_update_themes' ) ) {
			add_action( 'admin_init', '_maybe_update_themes' );
		}

		parent::tear_down_after_class();
	}

	private function invoke( string $method, ...$args ) {
		$reflection = new \ReflectionMethod( Console::get(), $method );
		return $reflection->invoke( Console::get(), ...$args );
	}

	private function scan( string $dir, int $max_depth, int $ceiling, int &$total ): array {
		$reflection = new \ReflectionMethod( Console::get(), 'scan_directory_bounded' );
		return $reflection->invokeArgs( Console::get(), array( $dir, $max_depth, $ceiling, &$total ) );
	}

	private function make_fixture_dir( string $name ): string {
		$upload_dir = wp_upload_dir();
		$dir        = wp_normalize_path( realpath( $upload_dir['basedir'] ) ?: $upload_dir['basedir'] ) . '/expressionlab-tests/' . $name;
		wp_mkdir_p( $dir );
		$this->fixtures[] = $dir;
		return $dir;
	}

	private function remove_tree( string $dir ): void {
		if ( is_link( $dir ) || is_file( $dir ) ) {
			unlink( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( new \DirectoryIterator( $dir ) as $item ) {
			if ( ! $item->isDot() ) {
				$this->remove_tree( $item->getPathname() );
			}
		}
		rmdir( $dir );
	}

	private function flatten( array $nodes ): array {
		$flat = array();
		foreach ( $nodes as $node ) {
			$flat[] = $node;
			if ( ! empty( $node['children'] ) ) {
				$flat = array_merge( $flat, $this->flatten( $node['children'] ) );
			}
		}
		return $flat;
	}

	private function find_node( array $nodes, string $path ): ?array {
		foreach ( $this->flatten( $nodes ) as $node ) {
			if ( $path === $node['path'] ) {
				return $node;
			}
		}
		return null;
	}

	public function test_get_filesystem_without_nonce_is_rejected() {
		$_GET = array( 'action' => 'expressionlab_get_filesystem' );

		try {
			$this->_handleAjax( 'expressionlab_get_filesystem' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
			$this->assertSame( 'Unauthorized', $response['data']['message'] );
			$this->assertArrayNotHasKey( 'presets', $response['data'] );
		}
	}

	public function test_get_filesystem_rejects_admin_not_matching_configured_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET = array(
			'action' => 'expressionlab_get_filesystem',
			'nonce'  => wp_create_nonce( Console::NONCE_ACTION ),
		);
		$_REQUEST = $_GET;

		try {
			$this->_handleAjax( 'expressionlab_get_filesystem' );
			$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
		} catch ( \WPAjaxDieContinueException $e ) {
			$response = json_decode( $this->_last_response, true );
			$this->assertFalse( $response['success'] );
			$this->assertSame( 'Unauthorized', $response['data']['message'] );
		} finally {
			$_REQUEST = array();
		}
	}

	public function test_challenge_params_are_read_only_from_the_signed_source() {
		$_GET  = array( 'challenge_nonce' => 'from-query', 'timestamp' => '111' );
		$_POST = array( 'challenge_nonce' => 'from-body', 'timestamp' => '222' );

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertSame( array( 'from-query', '111' ), $this->invoke( 'get_signed_challenge_params' ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertSame( array( 'from-body', '222' ), $this->invoke( 'get_signed_challenge_params' ) );

		$_POST = array();
		$this->assertSame( array( '', '' ), $this->invoke( 'get_signed_challenge_params' ) );
	}

	public function test_normalize_rel_path() {
		$root = wp_normalize_path( realpath( ABSPATH ) );

		$this->assertSame( '.', $this->invoke( 'normalize_rel_path', ABSPATH, true ) );
		$this->assertSame( 'wp-load.php', $this->invoke( 'normalize_rel_path', ABSPATH . 'wp-load.php', true ) );
		$this->assertSame( 'wp-content', $this->invoke( 'normalize_rel_path', $root . '/wp-content/', true ) );
		$this->assertSame( 'wp-content/missing-xyz.log', $this->invoke( 'normalize_rel_path', WP_CONTENT_DIR . '/missing-xyz.log', false ) );
		$this->assertNull( $this->invoke( 'normalize_rel_path', WP_CONTENT_DIR . '/missing-xyz.log', true ) );
		$this->assertNull( $this->invoke( 'normalize_rel_path', '/etc/passwd', false ) );
		$this->assertNull( $this->invoke( 'normalize_rel_path', ABSPATH . '../outside.txt', false ) );
		$this->assertNull( $this->invoke( 'normalize_rel_path', 'phar://' . ABSPATH . 'wp-load.php', false ) );
		$this->assertNull( $this->invoke( 'normalize_rel_path', ABSPATH . "wp-load.php\0.txt", false ) );
	}

	public function test_build_filesystem_presets_contract() {
		$presets = $this->invoke( 'build_filesystem_presets' );
		$allowed = array( 'dir', 'log', 'config', 'php', 'css', 'json', 'server', 'text' );

		$this->assertNotEmpty( $presets );
		$ids = array_column( $presets, 'id' );
		$this->assertSame( $ids, array_unique( $ids ) );

		foreach ( $presets as $preset ) {
			foreach ( array( 'id', 'name', 'path', 'exists', 'type', 'size', 'description' ) as $key ) {
				$this->assertArrayHasKey( $key, $preset );
			}
			$this->assertContains( $preset['type'], $allowed );
			$this->assertIsBool( $preset['exists'] );
			$this->assertStringStartsNotWith( '/', $preset['path'] );
			$this->assertStringNotContainsString( '..', $preset['path'] );
			if ( ! $preset['exists'] || 'dir' === $preset['type'] ) {
				$this->assertNull( $preset['size'] );
			}
		}

		$by_id = array_column( $presets, null, 'id' );
		$this->assertSame( 'dir', $by_id['mu_plugins']['type'] );
		$this->assertSame( 'dir', $by_id['plugins_dir']['type'] );
		$this->assertSame( 'log', $by_id['debug_log']['type'] );
		$this->assertSame( 'wp-config.php', $by_id['wp_config']['path'] );
	}

	public function test_bounded_tree_structure_and_confinement() {
		$tree = $this->invoke( 'build_bounded_filesystem_tree' );
		$flat = $this->flatten( $tree );

		$this->assertNotEmpty( $tree );
		$this->assertLessThanOrEqual( 200, count( $flat ) );

		foreach ( $flat as $node ) {
			$this->assertStringStartsNotWith( '/', $node['path'] );
			$this->assertStringNotContainsString( '..', $node['path'] );
			$this->assertNotEmpty( realpath( ABSPATH . $node['path'] ) );
			if ( 'dir' === $node['type'] && $node['opaque'] ) {
				$this->assertSame( array(), $node['children'] );
			}
		}

		foreach ( array( 'wp-admin', 'wp-includes' ) as $core_dir ) {
			$node = $this->find_node( $tree, $core_dir );
			if ( null !== $node ) {
				$this->assertTrue( $node['opaque'] );
			}
		}

		$wp_content = $this->find_node( $tree, 'wp-content' );
		$this->assertNotNull( $wp_content );
		$this->assertFalse( $wp_content['opaque'] );

		$uploads = $this->find_node( $tree, 'wp-content/uploads' );
		if ( null !== $uploads ) {
			$this->assertTrue( $uploads['opaque'] );
			$this->assertSame( array(), $uploads['children'] );
		}
	}

	public function test_uploads_is_never_recursed() {
		$dir = $this->make_fixture_dir( 'tree-uploads-probe' );
		file_put_contents( "{$dir}/probe.txt", 'x' );

		foreach ( $this->flatten( $this->invoke( 'build_bounded_filesystem_tree' ) ) as $node ) {
			$this->assertStringStartsNotWith( 'wp-content/uploads/', $node['path'] );
		}
	}

	public function test_scan_directory_bounded_enforces_ceiling_across_levels() {
		$dir = $this->make_fixture_dir( 'tree-cap' );
		for ( $i = 1; $i <= 5; $i++ ) {
			mkdir( "{$dir}/sub{$i}" );
			for ( $j = 1; $j <= 10; $j++ ) {
				file_put_contents( "{$dir}/sub{$i}/file{$j}.php", '<?php' );
			}
			file_put_contents( "{$dir}/root{$i}.php", '<?php' );
		}

		$total = 0;
		$nodes = $this->scan( $dir, 3, 20, $total );

		$this->assertSame( 20, $total );
		$this->assertCount( 20, $this->flatten( $nodes ) );
		$this->assertCount( 5, array_filter( $nodes, fn( $n ) => 'file' === $n['type'] ) );
	}

	public function test_scan_directory_bounded_respects_depth_and_exclusions() {
		$dir = $this->make_fixture_dir( 'tree-depth' );
		wp_mkdir_p( "{$dir}/a/b/c" );
		file_put_contents( "{$dir}/a/b/c/deep.php", '<?php' );
		foreach ( array( 'node_modules/pkg', 'vendor/lib', 'assets/images', 'assets/fonts', 'assets/js', 'fonts' ) as $sub ) {
			wp_mkdir_p( "{$dir}/{$sub}" );
			file_put_contents( "{$dir}/{$sub}/file.txt", 'x' );
		}

		$total = 0;
		$nodes = $this->scan( $dir, 2, 200, $total );
		$names = array_map( fn( $n ) => $n['path'], $this->flatten( $nodes ) );
		$rel   = ltrim( str_replace( wp_normalize_path( realpath( ABSPATH ) ), '', $dir ), '/' );

		$b = $this->find_node( $nodes, "{$rel}/a/b" );
		$this->assertNotNull( $b );
		$this->assertTrue( $b['opaque'] );
		$this->assertSame( array(), $b['children'] );
		$this->assertNotContains( "{$rel}/a/b/c", $names );

		$this->assertNotContains( "{$rel}/node_modules", $names );
		$this->assertNotContains( "{$rel}/vendor", $names );
		$this->assertNotContains( "{$rel}/assets/images", $names );
		$this->assertNotContains( "{$rel}/assets/fonts", $names );
		$this->assertContains( "{$rel}/assets/js", $names );
		$this->assertContains( "{$rel}/fonts", $names );
	}

	public function test_scan_directory_bounded_skips_symlinked_directories() {
		$dir = $this->make_fixture_dir( 'tree-symlink' );
		mkdir( "{$dir}/real" );
		file_put_contents( "{$dir}/real/file.php", '<?php' );

		if ( ! @symlink( $dir, "{$dir}/real/loop" ) || ! @symlink( '/etc', "{$dir}/escape" ) ) {
			$this->markTestSkipped( 'Symlinks are not supported on this filesystem.' );
		}

		$total = 0;
		$nodes = $this->scan( $dir, 3, 200, $total );
		$names = array_column( $this->flatten( $nodes ), 'name' );

		$this->assertContains( 'file.php', $names );
		$this->assertNotContains( 'loop', $names );
		$this->assertNotContains( 'escape', $names );
	}
}
