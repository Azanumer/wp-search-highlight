<?php
/**
 * Plugin Name:       Search Highlight
 * Plugin URI:        https://github.com/Azanumer/wp-search-highlight
 * Description:       Highlights the searched terms on WordPress search results pages — wrapped in <mark>, styled with one small CSS rule, with a settings toggle and a wsh_terms filter.
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           MIT
 * Text Domain:       wp-search-highlight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSH_VERSION', '1.0.0' );
define( 'WSH_OPTION', 'wsh_settings' );

/**
 * Default settings.
 *
 * @return array
 */
function wsh_get_settings() {
	$defaults = array(
		'enabled'      => 1,
		'highlight_in' => 'content', // 'content' (post content only) or 'all' (content + excerpts).
		'css_class'    => 'search-highlight',
	);
	$saved = get_option( WSH_OPTION, array() );
	return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
}

/**
 * Search terms for the current query, longest first so multi-word phrases
 * win over their single-word parts.
 *
 * @return string[]
 */
function wsh_search_terms() {
	$query = get_search_query( false );
	if ( '' === trim( $query ) ) {
		return array();
	}
	// Split on whitespace, drop one-letter noise, de-duplicate.
	$terms = array_unique( array_filter( preg_split( '/\s+/', $query ), function ( $term ) {
		return mb_strlen( $term ) >= 2;
	} ) );
	// Longest first: "wordpress hosting" highlights as one phrase before "wordpress".
	usort( $terms, function ( $a, $b ) {
		return mb_strlen( $b ) - mb_strlen( $a );
	} );
	/**
	 * Filter the terms to highlight.
	 *
	 * @param string[] $terms Search terms.
	 */
	return apply_filters( 'wsh_terms', $terms );
}

/**
 * Highlight terms in a chunk of HTML without touching tags or attributes.
 *
 * The HTML is split on tags; only text nodes are searched, so a term that
 * appears inside an attribute (e.g. an image alt) is never wrapped and
 * markup can never be broken.
 *
 * @param string   $html  HTML to highlight.
 * @param string[] $terms Terms to highlight.
 * @param string   $class CSS class for the <mark> tag.
 * @return string
 */
function wsh_highlight_html( $html, $terms, $class ) {
	if ( empty( $terms ) || '' === $html ) {
		return $html;
	}
	$pattern = '/' . implode( '|', array_map( function ( $term ) {
		return preg_quote( $term, '/' );
	}, $terms ) ) . '/iu';
	$parts = preg_split( '/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parts as &$part ) {
		if ( '' === $part || '<' === $part[0] ) {
			continue; // A tag, not text — leave it alone.
		}
		$part = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $class ) {
				return '<mark class="' . esc_attr( $class ) . '">' . $matches[0] . '</mark>';
			},
			$part
		);
	}
	return implode( '', $parts );
}

/**
 * Highlight in post content on search results pages only.
 *
 * @param string $content Post content.
 * @return string
 */
function wsh_highlight_content( $content ) {
	if ( ! is_search() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$settings = wsh_get_settings();
	if ( ! $settings['enabled'] ) {
		return $content;
	}
	return wsh_highlight_html( $content, wsh_search_terms(), $settings['css_class'] );
}
add_filter( 'the_content', 'wsh_highlight_content' );

/**
 * Optionally highlight in excerpts too (opt-in via settings).
 *
 * @param string $excerpt Post excerpt.
 * @return string
 */
function wsh_highlight_excerpt( $excerpt ) {
	if ( 'all' !== wsh_get_settings()['highlight_in'] ) {
		return $excerpt;
	}
	return wsh_highlight_content( $excerpt );
}
add_filter( 'the_excerpt', 'wsh_highlight_excerpt' );

/**
 * Print the default highlight style (theme-overridable via the CSS class).
 */
function wsh_print_style() {
	if ( ! is_search() ) {
		return;
	}
	$class = wsh_get_settings()['css_class'];
	echo '<style>mark.' . esc_attr( $class ) . ' { background: #ffeb3b; color: inherit; padding: 0 1px; border-radius: 2px; }</style>';
}
add_action( 'wp_head', 'wsh_print_style' );

/**
 * Settings page under Settings → Search Highlight.
 */
function wsh_register_settings() {
	register_setting( 'wsh_settings_group', WSH_OPTION );
	add_settings_section( 'wsh_main', '', '__return_false', 'wsh-settings' );
	add_settings_field( 'wsh_enabled', __( 'Enable highlighting', 'wp-search-highlight' ), 'wsh_field_enabled', 'wsh-settings', 'wsh_main' );
	add_settings_field( 'wsh_scope', __( 'Highlight in', 'wp-search-highlight' ), 'wsh_field_scope', 'wsh-settings', 'wsh_main' );
}
add_action( 'admin_init', 'wsh_register_settings' );

/**
 * Add the settings page (menu registration only — kept separate from
 * admin_init so the page is not registered twice).
 */
function wsh_add_menu() {
	add_options_page(
		__( 'Search Highlight', 'wp-search-highlight' ),
		__( 'Search Highlight', 'wp-search-highlight' ),
		'manage_options',
		'wsh-settings',
		'wsh_render_settings'
	);
}
add_action( 'admin_menu', 'wsh_add_menu' );

function wsh_field_enabled() {
	$settings = wsh_get_settings();
	echo '<input type="checkbox" name="' . esc_attr( WSH_OPTION ) . '[enabled]" value="1"' . checked( $settings['enabled'], 1, false ) . ' />';
}

function wsh_field_scope() {
	$settings = wsh_get_settings();
	echo '<select name="' . esc_attr( WSH_OPTION ) . '[highlight_in]">';
	echo '<option value="content"' . selected( $settings['highlight_in'], 'content', false ) . '>' . esc_html__( 'Post content only', 'wp-search-highlight' ) . '</option>';
	echo '<option value="all"' . selected( $settings['highlight_in'], 'all', false ) . '>' . esc_html__( 'Content + excerpts', 'wp-search-highlight' ) . '</option>';
	echo '</select>';
}

function wsh_render_settings() {
	echo '<div class="wrap"><h1>' . esc_html__( 'Search Highlight', 'wp-search-highlight' ) . '</h1>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'wsh_settings_group' );
	do_settings_sections( 'wsh-settings' );
	submit_button();
	echo '</form></div>';
}
