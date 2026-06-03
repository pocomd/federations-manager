<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when xmlsectool fails to sign an XML document.
 */
final class XmlSigningException extends RuntimeException {}
