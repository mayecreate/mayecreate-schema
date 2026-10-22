<?php
/**
 * Prints the graph as one JSON-LD script in <head>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'mcs_output', 20 );

function mcs_output() {
	if ( is_admin() || is_feed() || ! function_exists( 'get_field' ) ) {
		return;
	}
	if ( mcs_opt( 'disable', false ) || ! apply_filters( 'mcs_enabled', true ) ) {
		return;
	}

	$graph = mcs_build_graph();
	if ( ! $graph ) {
		return;
	}

	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
	if ( mcs_opt( 'pretty', false ) ) {
		$flags |= JSON_PRETTY_PRINT;
	}

	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
		$flags
	);

	if ( ! $json ) {
		return;
	}

	// Never let content close the script tag early.
	$json = str_replace( '</', '<\/', $json );

	echo "\n<!-- MayeCreate Schema " . esc_html( MCS_VERSION ) . " -->\n";
	echo '<script type="application/ld+json" class="mayecreate-schema">' . "\n" . $json . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
