<?php

/**
 * Aphiria
 *
 * @link      https://www.aphiria.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/aphiria/api/blob/master/LICENSE.md
 */

namespace Aphiria\Api\Exceptions;

use Exception;

/**
 * Defines the interface for exception loggers to implement
 */
interface IExceptionLogger
{
    /**
     * Logs an error
     *
     * @param int $level The level of the error
     * @param string $message The message
     * @param string $file The file the error occurred in
     * @param int $line The line number the error occurred at
     * @param array $context The symbol table
     */
    public function logError(int $level, string $message, string $file = '', int $line = 0, array $context = []): void;

    /**
     * Logs an exception
     *
     * @param Exception $ex The exception to log
     */
    public function logException(Exception $ex): void;
}
