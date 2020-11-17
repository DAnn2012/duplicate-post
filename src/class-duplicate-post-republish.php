<?php
/**
 * Duplicate Post republish class.
 *
 * @package Duplicate_Post
 */

namespace Yoast\WP\Duplicate_Post;

use Yoast\WP\Duplicate_Post\Duplicate_Post_Utils;

/**
 * Represents the Duplicate Post Republish class.
 */
class Duplicate_Post_Republish {

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Adds hooks to integrate with WordPress.
	 *
	 * @return void
	 */
	private function register_hooks() {
		\add_action( 'init', array( $this, 'register_post_statuses' ) );
		\add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data_before_wp_insert' ), 10, 2 );

		$enabled_post_types = Duplicate_Post_Utils::get_post_types_enabled_for_copy();
		foreach ( $enabled_post_types as $enabled_post_type ) {
			\add_action( "rewrite_republish_{$enabled_post_type}", array( $this, 'duplicate_post_republish' ), 10, 2 );
		}
	}

	/**
	 * Handles the republishing flow.
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 *
	 * @return void
	 */
	public function duplicate_post_republish( $post_id, $post ) {
		$this->republish_post_elements( $post_id, $post );
	}

	/**
	 * Republishes the post elements overwriting the original post.
	 *
	 * @param int      $post_copy_id Post ID.
	 * @param \WP_Post $post_copy    Post object.
	 *
	 * @return void
	 */
	private function republish_post_elements( $post_copy_id, $post_copy ) {

		$original_post_id = Duplicate_Post_Utils::get_rewrite_republish_copy_id( $post_copy_id );

		if ( ! $original_post_id ) {
			return;
		}

		$post_to_be_rewritten              = $post_copy;
		$post_to_be_rewritten->ID          = $original_post_id;
		$post_to_be_rewritten->post_name   = \get_post_field( 'post_name', $post_to_be_rewritten->ID );
		$post_to_be_rewritten->post_status = 'publish';

		// This section of code is partially duplicated from copy_post_taxonomies() which maybe should be better abstracted.
		$post_taxonomies = \get_object_taxonomies( $post_copy->post_type );
		foreach ( $post_taxonomies as $taxonomy ) {
			$post_terms = \wp_get_object_terms( $post_copy_id, $taxonomy, array( 'orderby' => 'term_order' ) );
			$terms      = array();
			$num_terms  = count( $post_terms );
			for ( $i = 0; $i < $num_terms; $i++ ) {
				$terms[] = $post_terms[ $i ]->slug;
			}
			\wp_set_object_terms( $post_to_be_rewritten->ID, $terms, $taxonomy );
		}
		// End of duplicated code section.

		$rewritten_post_id = \wp_update_post( \wp_slash( (array) $post_to_be_rewritten ), true );

		if ( 0 === $rewritten_post_id || \is_wp_error( $rewritten_post_id ) ) {
			// Error handling here.
			die( 'An error occurred.' );
		}

		// Deleting the copy bypassing the trash also deletes the post copy meta.
		\wp_delete_post( $post_copy_id, true );

		// Add nonce verification here.
		\wp_safe_redirect(
			\add_query_arg(
				array(
					'republished' => 1,
				),
				\admin_url( 'post.php?action=edit&post=' . $original_post_id )
			)
		);
		exit();
	}

	/**
	 * Adds custom post statuses.
	 *
	 * @return void
	 */
	public function register_post_statuses() {
		$republish_args = array(
			'label'    => __( 'Republish', 'duplicate-post' ),
			'internal' => true,
		);
		\register_post_status( 'rewrite_republish', $republish_args );

		$schedule_args = array(
			'label'    => __( 'Future Republish', 'duplicate-post' ),
			'internal' => true,
		);
		\register_post_status( 'rewrite_schedule', $schedule_args );
	}

	/**
	 * Adds custom statuses to the copied post.
	 *
	 * @param array $data    An array of slashed, sanitized, and processed post data.
	 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
	 *
	 * @return array An array of slashed, sanitized, and processed attachment post data.
	 */
	public function filter_post_data_before_wp_insert( $data, $postarr ) {
		if ( ! isset( $postarr['ID'] ) || ! Duplicate_Post_Utils::get_rewrite_republish_copy_id( $postarr['ID'] ) ) {
			return $data;
		}

		if ( $data['post_status'] === 'publish' ) {
			$data['post_status'] = 'rewrite_republish';
		}

		if ( $data['post_status'] === 'future' ) {
			$data['post_status'] = 'rewrite_schedule';
		}

		return $data;
	}
}
