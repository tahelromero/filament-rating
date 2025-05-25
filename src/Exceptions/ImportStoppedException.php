<?php

namespace EightyNine\ExcelImport\Exceptions;

use Exception;

class ImportStoppedException extends Exception
{
    public function __construct(
        public string $userMessage,
        public string $type = 'error',
        $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($userMessage, $code, $previous);
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
