<?php
/**
 * Gateway exception.
 *
 * @package Kalenda
 */

declare( strict_types=1 );

namespace Kalenda\Exceptions;

use RuntimeException;

/**
 * Thrown when the liturgical calendar data source cannot fulfil a request.
 *
 * Wraps transport errors, non-200 responses and malformed payloads behind a
 * single plugin-owned type so callers never have to catch vendor exceptions.
 */
final class GatewayException extends RuntimeException {}
