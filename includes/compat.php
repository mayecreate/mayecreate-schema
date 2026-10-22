<?php
/**
 * Prevents duplicate schema from other plugins.
 *
 * - Yoast SEO and Slim SEO: their schema is switched off automatically (their other SEO features keep working).
 * - Rank Math, AIOSEO, Schema & Structured Data for WP, Schema Pro: an admin warning asks you to turn them off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Yoast SEO: remove its JSON-LD graph.
add_filter( 'wpseo_json_ld_output', '__return_false', 99 );

// Slim SEO: remove its schema output.
add_action(
	'slim_seo_init',
	function ( $plugin ) {
		if ( is_object( $plugin ) && method_exists( $plugin, 'disable' ) ) {
			$plugin->disable( 'schema' );
		}
	}
);

// Rank Math: remove its JSON-LD.
add_filter( 'rank_math/json_ld', '__return_empty_array', 99 );

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$conflicts = array(
			'schema-and-structured-data-for-wp/structured-data-for-wp.php' => 'Schema & Structured Data for WP & AMP',
			'wp-schema-pro/wp-schema-pro.php'                              => 'Schema Pro',
			'all-in-one-seo-pack/all_in_one_seo_pack.php'                  => 'All in One SEO',
			'all-in-one-seo-pack-pro/all_in_one_seo_pack.php'              => 'All in One SEO Pro',
		);

		$active = array();
		foreach ( $conflicts as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $name;
			}
		}

		if ( ! $active ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>MayeCreate Schema:</strong> %s also outputs schema, which will create duplicates. Deactivate it, or turn off its schema feature.</p></div>',
			esc_html( implode( ', ', $active ) )
		);
	}
);
