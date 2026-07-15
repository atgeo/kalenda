<?php
/**
 * Block registrar.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Blocks;

use Kalenda\Contracts\Registrable;
use const Kalenda\KALENDA_PATH;

/**
 * Registers Kalenda's Gutenberg blocks.
 */
final class BlockRegistrar implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register every Kalenda block.
	 */
	public function register_blocks(): void {
		register_block_type( KALENDA_PATH . 'build/day' );
	}
}
