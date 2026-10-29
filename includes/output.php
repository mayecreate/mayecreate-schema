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
	$ctx   = isset( $GLOBALS['mcs_ctx'] ) ? $GLOBALS['mcs_ctx'] : array();
	$note  = ! empty( $ctx['kind'] ) ? ' | page: ' . $ctx['kind'] . ( ! empty( $ctx['reason'] ) ? ' (' . $ctx['reason'] . ')' : '' ) : '';
	if ( ! $graph ) {
		echo "\n<!-- MayeCreate Schema " . esc_html( MCS_VERSION . $note ) . " | no schema on this page -->\n";
		return;
	}

	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
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
		echo "\n<!-- MayeCreate Schema " . esc_html( MCS_VERSION ) . " | could not encode schema: " . esc_html( json_last_error_msg() ) . " -->\n";
		return;
	}

	// Never let content close the script tag early.
	$json = str_replace( '</', '<\/', $json );

	echo "\n<!-- MayeCreate Schema " . esc_html( MCS_VERSION . $note ) . " -->\n";
	echo '<script type="application/ld+json" class="mayecreate-schema">' . "\n" . $json . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
