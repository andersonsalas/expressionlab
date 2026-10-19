<?php

// phpcs:ignoreFile

use ExpressionLab\Core\LanguageEngine;
use ExpressionLab\Core\Services\Database;
use ExpressionLab\Core\Services\Media;
use ExpressionLab\Core\Models\Attachment;

class MediaTest extends WP_UnitTestCase {

	/**
	 * Media instance.
	 *
	 * @var Media
	 */
	private $media;

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private $database;

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'SQLite3' ) ) {
			$this->markTestSkipped( 'SQLite3 extension is required.' );
		}

		$this->database = new Database();
		$this->media    = new Media( $this->database );
	}

	public function test_get_by_id_returns_attachment_model() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$result = $this->media->get( $attachment_id );

		$this->assertInstanceOf( Attachment::class, $result );
		$this->assertEquals( $attachment_id, $result->ID );
		$this->assertEquals( 'image/png', $result->mime_type );
	}

	public function test_get_by_url_original() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$url           = wp_get_attachment_url( $attachment_id );

		$result = $this->media->get( $url );

		$this->assertInstanceOf( Attachment::class, $result );
		$this->assertEquals( $attachment_id, $result->ID );
	}

	public function test_get_by_url_resized_thumbnail() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$url           = wp_get_attachment_url( $attachment_id );

		// Simulate a thumbnail URL like ".../test-image-300x200.png"
		$thumb_url = preg_replace( '/(\.[a-zA-Z0-9]+)$/', '-300x200$1', $url );

		$result = $this->media->get( $thumb_url );

		$this->assertInstanceOf( Attachment::class, $result );
		$this->assertEquals( $attachment_id, $result->ID );
	}

	public function test_get_by_url_scaled() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$url           = wp_get_attachment_url( $attachment_id );

		// Simulate a scaled URL like ".../test-image-scaled.png"
		$scaled_url = preg_replace( '/(\.[a-zA-Z0-9]+)$/', '-scaled$1', $url );

		$result = $this->media->get( $scaled_url );

		$this->assertInstanceOf( Attachment::class, $result );
		$this->assertEquals( $attachment_id, $result->ID );
	}

	public function test_get_by_relative_url() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$url           = wp_get_attachment_url( $attachment_id );
		$path          = wp_parse_url( $url, PHP_URL_PATH );

		$result = $this->media->get( $path );

		$this->assertInstanceOf( Attachment::class, $result );
		$this->assertEquals( $attachment_id, $result->ID );
	}

	public function test_attachment_model_methods_and_meta() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$attachment    = $this->media->get( $attachment_id );

		$this->assertNotNull( $attachment );
		$this->assertNotEmpty( $attachment->get_url() );
		$this->assertNotEmpty( $attachment->get_path() );
		$this->assertIsArray( $attachment->get_dimensions() );
		$this->assertIsArray( $attachment->get_value() );

		// Meta tests
		$attachment->meta->set( 'custom_media_meta', 'val_123' );
		$this->assertEquals( 'val_123', $attachment->meta->get( 'custom_media_meta' ) );

		$attachment->meta->delete( 'custom_media_meta' );
		$this->assertEmpty( $attachment->meta->get( 'custom_media_meta' ) );
	}

	public function test_media_delete_succeeds_when_write_permitted() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$result = $this->media->delete( $attachment_id, true );
		$this->assertTrue( $result );
		$this->assertNull( $this->media->get( $attachment_id ) );
	}

	public function test_attachment_delete_succeeds_when_write_permitted() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$attachment    = $this->media->get( $attachment_id );

		$result = $attachment->delete( true );
		$this->assertTrue( $result );
		$this->assertNull( $this->media->get( $attachment_id ) );
	}

	public function test_media_mime_types() {
		$this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$types = $this->media->mime_types();
		$this->assertIsArray( $types );
		$this->assertContains( 'image/png', $types );
	}

	public function test_media_stats() {
		$this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$stats = $this->media->stats();
		$this->assertIsArray( $stats );
		$this->assertNotEmpty( $stats );
	}

	public function test_media_query_builder_scopes_to_attachments() {
		// Create a regular post and a media attachment.
		$post_id       = $this->factory->post->create( array( 'post_title' => 'Standard Post' ) );
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$results = $this->media->where( 'ID', '>', 0 )->get();
		$this->assertIsArray( $results );

		$ids = array_map(
			function ( $row ) {
				return (int) ( is_object( $row ) ? $row->ID : $row['ID'] );
			},
			$results
		);

		$this->assertContains( $attachment_id, $ids );
		$this->assertNotContains( $post_id, $ids );

		// Test count respects attachment scoping.
		$count = $this->media->where( 'ID', '>', 0 )->count();
		$this->assertEquals( count( $ids ), $count );
	}

	public function test_attachment_show_generates_image_visualization() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$attachment    = $this->media->get( $attachment_id );

		$this->assertInstanceOf( Attachment::class, $attachment );

		LanguageEngine::get()->reset();
		$returned = $attachment->show();

		$this->assertSame( $attachment, $returned );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertNotEmpty( $visualizations );
		$last_viz = end( $visualizations );

		$this->assertEquals( 'image', $last_viz['type'] );
		$this->assertEquals( 'image/png', $last_viz['mime'] );
		$this->assertEquals( $attachment_id, $last_viz['attachment_id'] );
		$this->assertNotEmpty( $last_viz['base64'] );
	}

	public function test_media_show_method() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		LanguageEngine::get()->reset();
		$attachment = $this->media->show( $attachment_id );

		$this->assertInstanceOf( Attachment::class, $attachment );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertNotEmpty( $visualizations );
		$last_viz = end( $visualizations );

		$this->assertEquals( 'image', $last_viz['type'] );
		$this->assertEquals( $attachment_id, $last_viz['attachment_id'] );
	}

	public function test_media_show_invalid_returns_null() {
		$result = $this->media->show( 999999999 );
		$this->assertNull( $result );
	}

	public function test_media_get_automatically_attaches_visualization() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		LanguageEngine::get()->reset();
		$attachment = $this->media->get( $attachment_id );

		$this->assertInstanceOf( Attachment::class, $attachment );

		$visualizations = LanguageEngine::get()->get_visualizations();
		$this->assertNotEmpty( $visualizations );
		$last_viz = end( $visualizations );

		$this->assertEquals( 'image', $last_viz['type'] );
		$this->assertEquals( 'image/png', $last_viz['mime'] );
		$this->assertEquals( $attachment_id, $last_viz['attachment_id'] );
		$this->assertNotEmpty( $last_viz['base64'] );
	}

	public function test_attachment_get_value_includes_sizes() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );

		$attachment = $this->media->get( $attachment_id, false );
		$value      = $attachment->get_value();

		$this->assertIsArray( $value );
		$this->assertArrayHasKey( 'sizes', $value );
		$this->assertIsArray( $value['sizes'] );
	}

	public function test_attachment_show_exceeding_budget_throws() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		$attachment    = $this->media->get( $attachment_id, false );

		LanguageEngine::get()->reset();
		// Pre-allocate the entire budget
		LanguageEngine::get()->allocate_binary( LanguageEngine::MAX_EMBEDDED_BINARY_BYTES );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'budget' );
		$attachment->show();
	}

	public function test_attachment_show_blocks_stream_wrappers() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		update_post_meta( $attachment_id, '_wp_attached_file', 'phar://malicious.phar/image.png' );

		$attachment = $this->media->get( $attachment_id, false );
		$this->assertEquals( 0, $attachment->filesize );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'outside allowed boundary' );
		$attachment->show();
	}

	public function test_attachment_show_blocks_directory_traversal() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		update_post_meta( $attachment_id, '_wp_attached_file', '../../../../etc/passwd' );

		$attachment = $this->media->get( $attachment_id, false );
		$this->assertEquals( 0, $attachment->filesize );

		$this->expectException( \RuntimeException::class );
		$attachment->show();
	}

	public function test_attachment_orphan_file_handles_zero_filesize_cleanly() {
		$attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.png' );
		update_post_meta( $attachment_id, '_wp_attached_file', '2026/08/non_existent_orphan.png' );

		$attachment = $this->media->get( $attachment_id, false );
		$this->assertInstanceOf( Attachment::class, $attachment );
		$this->assertEquals( 0, $attachment->filesize );
		$this->assertEquals( '0 B', $attachment->filesize_human );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'physical file not found or unreadable' );
		$attachment->show();
	}
}

