<?php

namespace APP\plugins\generic\OASwitchboard\classes\exceptions;

class P1PioException extends Exception
{
    protected $errors;

    public function __construct($message = "", $code = 0, $errors = null, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getP1PioErrors()
    {
        return $this->errors;
    }
}
