<?php
/**
 * REST route registrar.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Rest;

use Kalenda\Contracts\Registrable;
use Kalenda\Contracts\RouteProvider;

/**
 * Registers the plugin's REST API on `rest_api_init`.
 *
 * A thin coordinator, not a request handler: it holds the endpoint controllers
 * ({@see RouteProvider}s) it is given and delegates route registration to each.
 * It never instantiates controllers itself, so the set of endpoints is decided
 * by whoever wires the plugin together.
 */
final class RestRegistrar implements Registrable {

	/**
	 * The REST namespace all Kalenda routes live under.
	 */
	public const REST_NAMESPACE = 'kalenda/v1';

	/**
	 * Endpoint controllers to register.
	 *
	 * @var RouteProvider[]
	 */
	private array $providers;

	/**
	 * Collect the endpoint controllers to register.
	 *
	 * @param RouteProvider ...$providers Endpoint controllers.
	 */
	public function __construct( RouteProvider ...$providers ) {
		$this->providers = $providers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register every provider's routes.
	 */
	public function register_routes(): void {
		foreach ( $this->providers as $provider ) {
			$provider->register_routes();
		}
	}
}
