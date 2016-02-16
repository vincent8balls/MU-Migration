<?php
/**
 *  @package TenUp\MU_Migration
 *
 */
namespace TenUp\MU_Migration\Commands;

use WP_CLI;

class PostsCommand extends MUMigrationBase {

	/**
	 * Updates all post_author values in all wp_posts records that have post_author != 0
	 *
	 * It uses a map_file, containing the new user ID for each old user ID. This map files should be passed to the
	 * command as an argument
	 *
	 * ## OPTIONS
	 *
	 * <inputfile>
	 * : The name of the json map file
	 *
	 * ## EXAMBLES
	 *
	 *   wp mu-migration posts update_author map_users.json --blog_id=2
	 *
	 * @synopsis <inputfile> --blog_id=<blog_id>
	 */
	public function update_author( $args = array(), $assoc_args = array() ) {
		$default_args = array(
			0 => '', // .json map file
		);

		$this->args = $args + $default_args;

		$filename = $this->args[0];

		if ( empty( $filename ) || ! file_exists( $filename ) ) {
			WP_CLI::error( __( "Invalid input file", 'mu-migration' ) );
		}

		$this->assoc_args = wp_parse_args( $assoc_args,
			array(
				'blog_id'  => '',
			)
		);

		if ( empty( $this->assoc_args['blog_id'] ) ) {
			WP_CLI::error( __( "Please, provide a blog id", 'mu-migration' ) );
		}

		switch_to_blog( $this->assoc_args['blog_id'] );

		$ids_map = json_decode( file_get_contents( $filename ) );

		if ( NULL === $ids_map ) {
			WP_CLI::error(
				__( 'An error has occurred when parsing the json file', 'mu-migration' )
			);
		}

		global $wpdb;

		/**
		 * Grab records that should have an author, no matter the type of the post
		 */
		$sql  = "SELECT * FROM {$wpdb->posts} as wpp WHERE wpp.post_author != 0 ";

		$results = $wpdb->get_results( $sql );
		$counter = 0;

		$equals_id = array();
		$author_not_found = array();

		foreach( $results as $result ) {
			if ( isset( $ids_map->{$result->post_author} ) ) {
				if ( $result->ID != $ids_map->{$result->post_author} ) {
					$wpdb->update( $wpdb->posts,
						array(
							'post_author' => $ids_map->{$result->post_author}
						),
						array(
							'ID' => $result->ID
						),
						array(
							'%d'
						),
						array(
							'%d'
						)
					);

					WP_CLI::log( sprintf(
						__( 'Updated post_author for "%s" (ID #%d)', 'mu-migration' ),
						$result->post_title,
						absint( $result->ID )
					) );

					$counter++;
				} else {
					WP_CLI::log( sprintf(
						__( '#%d New user ID equals to the old user ID'),
						$result->ID
					) );
					$equals_id[] = $result->ID;
				}

			} else {
				WP_CLI::log( sprintf(
					__( "#%d New user ID not found or it's already been updated", 'mu-migration'),
					$result->ID
				) );

				$author_not_found[] = $result->ID;
			}

		}


		//Report
		if ( ! empty( $author_not_found ) ) {
			WP_CLI::warning( sprintf(
				__( '%d records failed to update its post_author %s', 'mu-migration' ),
				count( $author_not_found ),
				implode( ',', $author_not_found )
			) );
		}

		if ( ! empty( $equals_id ) ) {
			WP_CLI::warning( sprintf(
				__( 'The following records have the new ID equal to the old ID', 'mu-migration' ),
				implode( ',', $equals_id )
			) );
		}

		WP_CLI::success( sprintf(
			__( '%d posts has been updated', 'mu-migration' ),
			$counter
		) );

		restore_current_blog();

	}

	/**
	 * Updates all wc customer_user values in all wp_posts records that have a customer user set
	 *
	 * It uses a map_file, containing the new user ID for each old user ID. This map files should be passed to the
	 * command as an argument
	 *
	 * ## OPTIONS
	 *
	 * <inputfile>
	 * : The name of the json map file
	 *
	 * ## EXAMBLES
	 *
	 *   wp mu-migration posts update_wc_customer map_users.json --blog_id=2
	 *
	 * @synopsis <inputfile> --blog_id=<blog_id>
	 */
	public function update_wc_customer( $args = array(), $assoc_args = array() ) {
		$default_args = array(
			0 => '', // .json map file
		);

		$this->args = $args + $default_args;

		$filename = $this->args[0];

		if ( empty( $filename ) || ! file_exists( $filename ) ) {
			WP_CLI::error( __( "Invalid input file", 'mu-migration' ) );
		}

		$this->assoc_args = wp_parse_args( $assoc_args,
			array(
				'blog_id'  => '',
			)
		);

		if ( empty( $this->assoc_args['blog_id'] ) ) {
			WP_CLI::error( __( "Please, provide a blog id", 'mu-migration' ) );
		}

		switch_to_blog( $this->assoc_args['blog_id'] );

		$ids_map = json_decode( file_get_contents( $filename ) );

		if ( NULL === $ids_map ) {
			WP_CLI::error(
				__( 'An error has occurred when parsing the json file', 'mu-migration' )
			);
		}

		$this->all_posts(
			array(
				'post_type'     => wc_get_order_types(),
				'post_status'   => array_keys( wc_get_order_statuses() )
			),
			function() use ( $ids_map ) {
				$old_customer_user = get_post_meta( get_the_ID(), '_customer_user', true );

				if ( isset( $ids_map->{$old_customer_user} ) && $old_customer_user != $ids_map->{$old_customer_user} ) {
					$new_customer_user = $ids_map->{$old_customer_user};

					update_post_meta( get_the_ID(), '_customer_user', $new_customer_user );

					WP_CLI::log( sprintf(
						__( 'Updated customer_user for "%s" (ID #%d)', 'mu-migration' ),
						get_the_title(),
						absint( get_the_ID() )
					) );
			}
		} );

		restore_current_blog();
	}
}

WP_CLI::add_command( 'mu-migration posts', __NAMESPACE__ . '\\PostsCommand' );