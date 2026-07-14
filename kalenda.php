<?php
/**
 * Kalenda
 *
 * @package           Kalenda
 * @author            Georges Kmeid
 * @copyright         2026 Georges Kmeid
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Kalenda
 * Plugin URI:        https://github.com/atgeo/kalenda
 * Description:       Displays the Catholic liturgical calendar on any WordPress theme, powered by the LitCal API.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Georges Kmeid
 * Author URI:        https://github.com/atgeo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kalenda
 * Domain Path:       /languages
 */

declare( strict_types=1 );

namespace Kalenda;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Prevent double-loading (e.g. plugin present in two locations).
if ( defined( 'Kalenda\\KALENDA_VERSION' ) ) {
	return;
}

const KALENDA_VERSION     = '0.1.0';
const KALENDA_MINIMUM_PHP = '8.1';
const KALENDA_MINIMUM_WP  = '6.5';

define( 'Kalenda\\KALENDA_FILE', __FILE__ );
define( 'Kalenda\\KALENDA_PATH', plugin_dir_path( __FILE__ ) );
define( 'Kalenda\\KALENDA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Render a dismissible admin notice describing why the plugin could not boot.
 *
 * @param string $message Already-translated, plain-text message.
 */
function admin_notice( string $message ): void {
	add_action(
		'admin_notices',
		static function () use ( $message ): void {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Kalenda:', 'kalenda' ),
				esc_html( $message )
			);
		}
	);
}

/**
 * Verify the runtime meets the minimum requirements before booting.
 *
 * @return bool True when the environment is supported.
 */
function requirements_met(): bool {
	if ( version_compare( PHP_VERSION, KALENDA_MINIMUM_PHP, '<' ) ) {
		admin_notice(
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'requires PHP %1$s or newer. You are running PHP %2$s.', 'kalenda' ),
				KALENDA_MINIMUM_PHP,
				PHP_VERSION
			)
		);
		return false;
	}

	if ( version_compare( get_bloginfo( 'version' ), KALENDA_MINIMUM_WP, '<' ) ) {
		admin_notice(
			sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'requires WordPress %1$s or newer. Please update WordPress.', 'kalenda' ),
				KALENDA_MINIMUM_WP,
				get_bloginfo( 'version' )
			)
		);
		return false;
	}

	if ( ! is_readable( KALENDA_PATH . 'vendor/autoload.php' ) ) {
		admin_notice(
			__( 'is missing its Composer dependencies. Run "composer install" in the plugin directory.', 'kalenda' )
		);
		return false;
	}

	return true;
}

// Bail early (without fataling) when the environment is unsupported.
if ( ! requirements_met() ) {
	return;
}

require_once KALENDA_PATH . 'vendor/autoload.php';

/**
 * Boot the plugin once WordPress and all other plugins have loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
