<?php

// phpcs:ignoreFile

namespace ExpressionLab\Tests\Fixtures;

/**
 * Class DatabaseSeeder
 */
class DatabaseSeeder {

	/**
	 * Seed deterministic posts in bulk directly via $wpdb.
	 *
	 * @param int $total_rows Total number of deterministic posts to seed. Default is 5000.
	 */
	public static function seed_posts( int $total_rows = 5000 ) {
		global $wpdb;

		$current_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
		$rows_to_insert = $total_rows - $current_count;

		if ( $rows_to_insert <= 0 ) {
			return;
		}

		$batch_size = 1000;
		$values     = array();

		$statuses = array( 'publish', 'publish', 'publish', 'publish', 'publish', 'publish', 'draft', 'draft', 'pending', 'private' );
		$types    = array( 'post', 'post', 'post', 'post', 'post', 'post', 'post', 'page', 'page', 'custom_item' );
		$topics   = array(
			'WordPress Plugin Architecture',
			'MySQL Query Optimization',
			'In-Memory SQLite Caching',
			'Domain-Specific Language (DSL) Engine',
			'PHP Memory Buffer Management',
			'Graph Traversal and Recursive Queries',
			'High-Concurrency Server Design',
		);
		$prefixes = array(
			'Advanced Guide to',
			'Deep Dive into',
			'The Future of',
			'Myths and Facts about',
			'Design Patterns in',
			'Optimization Strategies for',
			'Engineering Secrets of',
		);

		for ( $i = 1; $i <= $rows_to_insert; $i++ ) {
			$post_num = $current_count + $i;
			$author   = ( $i % 2 ) + 1;
			$day_diff = ( $i * 17 ) % 730;
			$sec_diff = ( $i * 37 ) % 86400;
			$date_str = gmdate( 'Y-m-d H:i:s', strtotime( '2024-01-01 00:00:00' ) + ( $day_diff * 86400 ) + $sec_diff );

			$status = $statuses[ ( $i % 10 ) ];
			$type   = $types[ ( $i % 10 ) ];
			$title  = $prefixes[ ( $i % 7 ) ] . ' ' . $topics[ ( ( $i * 3 ) % 7 ) ] . ' #' . $post_num;
			$slug   = 'post-' . $post_num;
			$comm   = ( $i % 5 === 0 ) ? 'closed' : 'open';
			$count  = ( $i * 7 ) % 25;

			$content = '<!-- wp:paragraph --><p>Deterministic sample content for post #' . $post_num . ' used for performance benchmarking and memory safety limit validation.</p><!-- /wp:paragraph -->';
			$excerpt = 'Deterministic summary for sample post #' . $post_num;
			$guid    = 'http://localhost:8080/?p=' . $post_num;

			$values[] = $wpdb->prepare(
				'(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %d, %s, %s, %d)',
				$author,
				$date_str,
				$date_str,
				$content,
				$title,
				$excerpt,
				$status,
				$comm,
				'open',
				'',
				$slug,
				'',
				'',
				$date_str,
				$date_str,
				'',
				0,
				$guid,
				0,
				$type,
				'',
				$count
			);

			if ( count( $values ) >= $batch_size || $i === $rows_to_insert ) {
				$sql = "INSERT INTO {$wpdb->posts} (
					`post_author`, `post_date`, `post_date_gmt`, `post_content`, `post_title`,
					`post_excerpt`, `post_status`, `comment_status`, `ping_status`, `post_password`,
					`post_name`, `to_ping`, `pinged`, `post_modified`, `post_modified_gmt`,
					`post_content_filtered`, `post_parent`, `guid`, `menu_order`, `post_type`,
					`post_mime_type`, `comment_count`
				) VALUES " . implode( ', ', $values );

				$wpdb->query( $sql ); 
				$values = array();
			}
		}
	}

	/**
	 * Seed deterministic users and usermeta in bulk directly via $wpdb.
	 *
	 * @param int $total_rows Total number of deterministic users to seed. Default is 100.
	 */
	public static function seed_users( int $total_rows = 100 ) {
		global $wpdb;

		$current_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
		$max_id         = (int) $wpdb->get_var( "SELECT COALESCE(MAX(ID), 0) FROM {$wpdb->users}" );
		$rows_to_insert = $total_rows - $current_count;

		if ( $rows_to_insert <= 0 ) {
			return;
		}

		$batch_size  = 500;
		$user_values = array();
		$meta_values = array();

		$first_names = array( 'John', 'Jane', 'Alex', 'Chris', 'Taylor', 'Morgan' );
		$last_names  = array( 'Doe', 'Smith', 'Johnson', 'Williams', 'Brown', 'Davis' );

		$roles_map = array(
			1 => array(
				'role'  => 'administrator',
				'cap'   => 'a:1:{s:13:"administrator";b:1;}',
				'level' => '10',
			),
			2 => array(
				'role'  => 'editor',
				'cap'   => 'a:1:{s:6:"editor";b:1;}',
				'level' => '7',
			),
			3 => array(
				'role'  => 'author',
				'cap'   => 'a:1:{s:6:"author";b:1;}',
				'level' => '2',
			),
			4 => array(
				'role'  => 'contributor',
				'cap'   => 'a:1:{s:11:"contributor";b:1;}',
				'level' => '1',
			),
			0 => array(
				'role'  => 'subscriber',
				'cap'   => 'a:1:{s:10:"subscriber";b:1;}',
				'level' => '0',
			),
		);

		$cap_key = $wpdb->get_blog_prefix() . 'capabilities';
		$lvl_key = $wpdb->get_blog_prefix() . 'user_level';

		for ( $i = 1; $i <= $rows_to_insert; $i++ ) {
			$new_id    = $max_id + $i;
			$role_data = $roles_map[ $i % 5 ];
			$role_name = $role_data['role'];

			$first_name = $first_names[ ( $i % 6 ) ];
			$last_name  = $last_names[ ( $i % 6 ) ];
			$login      = $role_name . '_' . $new_id;
			$email      = $role_name . '_' . $new_id . '@example.com';
			$nicename   = $role_name . '-' . $new_id;
			$url        = 'https://example.com/user-' . $new_id;
			$disp_name  = $first_name . ' ' . $last_name . ' (' . $role_name . ' #' . $new_id . ')';

			$day_diff = ( $i * 13 ) % 730;
			$sec_diff = ( $i * 41 ) % 86400;
			$date_str = gmdate( 'Y-m-d H:i:s', strtotime( '2024-01-01 00:00:00' ) + ( $day_diff * 86400 ) + $sec_diff );

			// Password hash for 'password'
			$pass_hash = '$P$B6W8uE/M0hUj/1jS9qKk0Q1l8M/gZ1/';

			$user_values[] = $wpdb->prepare(
				'(%d, %s, %s, %s, %s, %s, %s, %s, %d, %s)',
				$new_id,
				$login,
				$pass_hash,
				$nicename,
				$email,
				$url,
				$date_str,
				'',
				0,
				$disp_name
			);

			// Add corresponding usermeta entries
			$meta_values[] = $wpdb->prepare( '(%d, %s, %s)', $new_id, 'first_name', $first_name );
			$meta_values[] = $wpdb->prepare( '(%d, %s, %s)', $new_id, 'last_name', $last_name );
			$meta_values[] = $wpdb->prepare( '(%d, %s, %s)', $new_id, 'nickname', $login );
			$meta_values[] = $wpdb->prepare( '(%d, %s, %s)', $new_id, 'description', 'Deterministic sample user #' . $new_id );
			$meta_values[] = $wpdb->prepare( '(%d, %s, %s)', $new_id, $cap_key, $role_data['cap'] );
			$meta_values[] = $wpdb->prepare( '(%d, %s, %s)', $new_id, $lvl_key, $role_data['level'] );

			if ( count( $user_values ) >= $batch_size || $i === $rows_to_insert ) {
				// Insert users batch
				$users_sql = "INSERT INTO {$wpdb->users} (
					`ID`, `user_login`, `user_pass`, `user_nicename`, `user_email`,
					`user_url`, `user_registered`, `user_activation_key`, `user_status`, `display_name`
				) VALUES " . implode( ', ', $user_values );
				$wpdb->query( $users_sql ); 
				$user_values = array();

				// Insert usermeta batch
				$meta_sql = "INSERT INTO {$wpdb->usermeta} (`user_id`, `meta_key`, `meta_value`) VALUES " . implode( ', ', $meta_values );
				$wpdb->query( $meta_sql ); 
				$meta_values = array();
			}
		}
	}
}
