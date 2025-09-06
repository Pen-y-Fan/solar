<?php

declare(strict_types=1);

namespace App\Support\Actions;

/**
 * Standard result wrapper for domain actions to promote consistency.
 * Immutable, PSR-12 compliant, PHPStan-friendly.
 */
final class ActionResult
{
    /** @var array<string,mixed>|null */
    private ?array $data;

    private bool $success;

    private ?string $message;

    private ?string $code;

    /**
     * @param array<string,mixed>|null $data
     */
    private function __construct(bool $success, ?string $message = null, ?array $data = null, ?string $code = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->code = $code;
    }

    /**
     * @param array<string,mixed>|null $data
     */
    public static function success(?array $data = null, ?string $message = null): self
    {
        return new self(true, $message, $data);
    }

    public static function failure(string $message, ?string $code = null): self
    {
        return new self(false, $message, null, $code);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }
}
