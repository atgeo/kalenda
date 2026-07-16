<?php
/**
 * Block registrar.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Blocks;

use Kalenda\Contracts\Registrable;

/**
 * Registers Kalenda's Gutenberg blocks.
 */
final class BlockRegistrar implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( $this, 'register_category' ) );
	}

	/**
	 * Register every Kalenda block.
	 */
	public function register_blocks(): void {
		register_block_type( constant( 'Kalenda\\KALENDA_PATH' ) . 'build/day' );
	}

	/**
	 * Register the Kalenda block category.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing block categories.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function register_category( array $categories ): array {
		$categories[] = array(
			'slug'  => 'kalenda',
			'title' => __( 'Kalenda', 'kalenda' ),
			'icon'  => null,
		);

		return $categories;
	}
}
