<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/api/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Aphiria\Api\Controllers;

use Exception;

/**
 * Defines the exception that's thrown when a scalar parameter fails to be converted
 */
final class FailedScalarParameterConversionException extends Exception
{
    // Don't do anything
}