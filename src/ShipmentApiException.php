<?php

namespace Riesenia\DPDShipment;

use Throwable;

class ShipmentApiException extends \RuntimeException
{
    /** @var array */
    protected $errors = [];

    /** @var bool */
    public $hasErrors;

    public function __construct($message = '', int $code = 0, Throwable $previous = null)
    {
        if (is_array($message)) {
            $this->errors = $message;
            $this->hasErrors = true;
            $message = 'Error has occurred while processing request.';
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Errors getter.
     *
     * @return array|string
     */
    public function getErrors()
    {
        return $this->errors;
    }
}