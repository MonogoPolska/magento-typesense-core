<?php
declare(strict_types=1);

namespace Exceptions;

use Exception;
use Throwable;

class ConnectionException extends Exception
{
    const CONNECTION_ERROR_MESSAGE = 'Unable to determine Typesense server health. Please check your connection';

    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        if (empty($message)) {
            $message = self::CONNECTION_ERROR_MESSAGE;
        }
        parent::__construct($message, $code, $previous);
    }
}
