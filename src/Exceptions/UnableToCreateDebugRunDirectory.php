<?php

namespace Dprc\Spider\Exceptions;

use Exception;
use Throwable;

class UnableToCreateDebugRunDirectory extends Exception {
    public function __construct( $message = "", $code = 0, Throwable $previous = NULL ) {
        parent::__construct( $message, $code, $previous );
    }
}