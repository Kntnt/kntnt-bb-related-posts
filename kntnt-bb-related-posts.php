<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Kntnt's Related Posts for Beaver Builder.
 * Plugin URI:        https://github.com/Kntnt/kntnt-bb-related-posts
 * Description:       Extends Beaver Builder post modules (e.g. post-grid, post-slider and post-carosuel) to complement the posts shown with posts selected with Advanced Customfield (ACF) relation field.
 * Version:           1.0.0
 * Author:            Thomas Barregren
 * Author URI:        https://www.kntnt.com/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       kntnt-bb-related-posts
 * Domain Path:       /languages
 */

namespace Kntnt\BB_Related_Posts;

defined( 'WPINC' ) && new Plugin();

final class Plugin {

	private $module_tab_lut = null;

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'run' ] );
	}

	public function run() {
		if ( class_exists( '\FLBuilder' ) && class_exists( '\ACF' ) ) {
			add_filter( 'fl_builder_register_settings_form', [ $this, 'register_settings_form' ], 10, 2 );
			add_filter( 'fl_builder_loop_query_args', [ $this, 'loop_query_args' ], 9999 );
		}
	}

	public function register_settings_form( $form, $module ) {
		if ( $this->is_valid( $module ) ) {
			$tabs = [
				'kntnt_bb_related_posts' => [
					'title' => __( 'Related posts', 'kntnt-bb-related-posts' ),
					'sections' => [
						'kntnt_bb_related_posts' => [
							'title' => '',
							'fields' => [
								'kntnt_bb_related_posts_placement' => [
									'type' => 'select',
									'label' => __( 'Insert ACF relation posts', 'kntnt-bb-related-posts' ),
									'default' => 'none',
									'options' => [
										'none' => __( 'None', 'kntnt-bb-related-posts' ),
										'before' => __( 'Before', 'kntnt-bb-related-posts' ),
										'after' => __( 'After', 'kntnt-bb-related-posts' ),
									],
									'toggle' => [
										'none' => [
											'fields' => [ 'posts_post', 'posts_page' ],
										],
										'before' => [
											'fields' => [ 'kntnt_bb_related_posts_key' ],
										],
										'after' => [
											'fields' => [ 'kntnt_bb_related_posts_key' ],
										],
									],
								],
								'kntnt_bb_related_posts_key' => [

									'type' => 'text',
									'label' => __( 'ACF relation field key', 'kntnt-bb-related-posts' ),
								],
							],
						],
					],
				],
			];
			$pos = array_search( $this->module_tab_lut[ $module ], array_keys( $form ) ) + 1;
			$form = array_slice( $form, 0, $pos, true ) + $tabs + array_slice( $form, $pos, null, true );
		}
		return $form;
	}

	public function loop_query_args( $args ) {

		if ( ! isset( $args['settings'] ) ) return $args;

		$settings = $args['settings'];

		if ( ! isset( $settings->kntnt_bb_related_posts_placement ) || 'none' == $settings->kntnt_bb_related_posts_placement ) return $args;

		if ( ! isset( $settings->kntnt_bb_related_posts_key ) || empty( $settings->kntnt_bb_related_posts_key ) ) return $args;

		// Get an array of ids to the relational posts.
		$relation_posts = get_field( trim( $settings->kntnt_bb_related_posts_key ), false, false );

		if ( ! $relation_posts ) return $args;

		// Get an array of ids to the posts returned by the custom query
		// excluding the relational posts.
		$args['fields'] = 'ids';
		$args['nopaging'] = true;
		$args['post__not_in'] += $relation_posts;
		unset( $args['post__in'] );
		$original_posts = ( new \WP_Query( $args ) )->posts;

		// Modify the query to return the relation and custom query posts.
		$args['post__in'] = $this->array_join( $relation_posts, $original_posts, 'after' == $settings->kntnt_bb_related_posts_placement );
		$args['post_type'] = 'any';
		$args['orderby'] = 'post__in';
		unset( $args['fields'] );
		unset( $args['nopaging'] );
		unset( $args['post__not_in'] );
		unset( $args['author__not_in'] );
		unset( $args['tax_query'] );
		unset( $args['order'] );

		return $args;

	}

	private function is_valid( $module ) {

		if ( null == $this->module_tab_lut ) {

			/* Filters an array of modules subject to this plugin. By default
			 * the array contains the post modules of Beaver Builder.
			 */
			$this->module_tab_lut = apply_filters( 'kntnt-bb-related-posts-modules', [
				'post-grid' => 'content',
				'post-slider' => 'content',
				'post-carousel' => 'content',
			] );

		}

		return key_exists( $module, $this->module_tab_lut );

	}

	/* Returns an indexed array with the union of values in $arr1 and $arr2 in
	 * the same order as read from left to right. If a value recurs, it is
	 * discharged; its first occurrence is kept. If $swap is true, values
	 * belonging to $arr1 is moved to the end of the resulting array.
	 */
	private function array_join( $arr1, $arr2, $swap ) {
		$arr1 = array_flip( $arr1 );
		$arr2 = array_flip( $arr2 );
		$arr = $arr1 + $arr2;
		if ( $swap ) {
			$pos = count( $arr1 );
			$arr = array_slice( $arr, $pos, null, true ) + array_slice( $arr, 0, $pos, true );
		}
		return array_keys( $arr );
	}

}
