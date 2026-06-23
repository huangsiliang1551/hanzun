<?php

declare(strict_types=1);

namespace app\common\exception;

use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $errorCode = 50001,
        private readonly array $meta = []
    ) {
        parent::__construct($message, $errorCode);
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }
}
