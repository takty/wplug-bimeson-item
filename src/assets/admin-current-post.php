<?php
/**
 * Utilities of Current Post in Admin.
 *
 * @package Wplug
 * @author Takuto Yanagida
 * @version 2024-03-22
 */

declare(strict_types=1);

namespace wplug;

if ( ! function_exists( '\wplug\get_admin_post_id' ) ) {
	/**
	 * Gets the post ID.
	 *
	 * @return int Post ID.
	 */
	function get_admin_post_id(): int {
		$id_g = $_GET['post']     ?? null;  // phpcs:ignore
		$id_p = $_POST['post_ID'] ?? null;  // phpcs:ignore

		if ( is_numeric( $id_g ) ) {
			return (int) $id_g;
		}
		if ( is_numeric( $id_p ) ) {
			return (int) $id_p;
		}
		return 0;
	}
}

if ( ! function_exists( '\wplug\get_admin_post_type' ) ) {
	/**
	 * Gets current post type.
	 *
	 * @return string|null Current post type.
	 */
	function get_admin_post_type(): ?string {
		$pt = null;

		$id = get_admin_post_id();
		if ( $id ) {
			$pt_ = get_post_type( $id );
			if ( is_string( $pt_ ) ) {
				$pt = $pt_;
			}
		}
		if ( null === $pt ) {
			$val = $_GET['post_type'] ?? null;  // phpcs:ignore
			if ( is_string( $val ) ) {
				$pt = $val;
			}
		}
		return $pt;
	}
}

if ( ! function_exists( '\wplug\is_admin_post_type' ) ) {
	/**
	 * Checks current post type.
	 *
	 * @param string $post_type Post type.
	 * @return bool True if the current post type is $post_type.
	 */
	function is_admin_post_type( string $post_type ): bool {
		return get_admin_post_type() === $post_type;
	}
}
