<?php
/**
 * Plugin Name:       AI Chat & Live Chat - All free
 * Plugin URI:        https://usefree.space
 * Description:       A free live chat widget with an AI chatbot. No account, no signup, no API key needed. Runs on a shared free token pool; bring your own key optionally.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Riemann Research
 * Author URI:        https://usefree.space
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bg-live-chat-free
 * Domain Path:       /languages
 */

// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (c) 2026 Riemann Research. See LICENSE.

defined( 'ABSPATH' ) || exit;

define( 'BGLC_VERSION', '1.1.0' );
define( 'BGLC_FILE', __FILE__ );
define( 'BGLC_DIR', plugin_dir_path( __FILE__ ) );
define( 'BGLC_URL', plugin_dir_url( __FILE__ ) );
define( 'BGLC_BASENAME', plugin_basename( __FILE__ ) );

require_once BGLC_DIR . 'includes/class-pool.php';
require_once BGLC_DIR . 'includes/class-store.php';
require_once BGLC_DIR . 'includes/class-rest.php';
require_once BGLC_DIR . 'includes/class-settings.php';
require_once BGLC_DIR . 'includes/class-inbox.php';
require_once BGLC_DIR . 'includes/class-widget.php';

/**
 * Boot the plugin once all classes are loaded.
 */
function bglc_init() {
	load_plugin_textdomain( 'bg-live-chat-free', false, dirname( BGLC_BASENAME ) . '/languages' );

	BGLC_Settings::instance()->register_hooks();
	BGLC_Inbox::instance()->register_hooks();
	BGLC_REST::instance()->register_hooks();
	BGLC_Widget::instance()->register_hooks();
}
add_action( 'plugins_loaded', 'bglc_init' );

/**
 * Create the chat tables and seed defaults on activation.
 */
function bglc_activate() {
	BGLC_Store::install();

	if ( false === get_option( 'bglc_baseurl', false ) ) {
		add_option( 'bglc_baseurl', 'https://api.usefree.space/v1' );
		add_option( 'bglc_maxtokens', 512 );
	}

	// Sensible first-run widget defaults.
	$defaults = array(
		'bglc_bot_enabled'  => 1,
		'bglc_tab_chat'     => 1,
		'bglc_floating'     => 1,
		'bglc_prechat'      => 0,
		'bglc_color'        => '#2271b1',
		'bglc_position'     => 'right',
		'bglc_headline'     => 'How can we help?',
		'bglc_greeting'     => 'Hi there! Ask us anything.',
		'bglc_bot_prompt'   => 'You are a friendly website support assistant. Answer clearly and concisely. If you do not know, say so and offer to connect the visitor with a human.',
	);
	foreach ( $defaults as $k => $v ) {
		if ( false === get_option( $k, false ) ) {
			add_option( $k, $v );
		}
	}
}
register_activation_hook( __FILE__, 'bglc_activate' );
