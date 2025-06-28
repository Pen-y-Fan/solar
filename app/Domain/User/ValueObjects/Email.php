<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Value object representing an email address with verification status
 */
class Email
{
    /**
     * @param string $address Email address
     * @param CarbonImmutable|null $verifiedAt When the email was verified (null if not verified)
     */
    public function __construct(
        public readonly string $address,
        public readonly ?CarbonImmutable $verifiedAt = null
    ) {
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
    }

    /**
     * Create from an array with 'email' and 'email_verified_at' keys
     */
    public static function fromArray(array $data): self
    {
        return new self(
            address: $data['email'],
            verifiedAt: isset($data['email_verified_at'])
                ? (($data['email_verified_at'] instanceof CarbonInterface)
                    ? ($data['email_verified_at'] instanceof CarbonImmutable
                        ? $data['email_verified_at']
                        : $data['email_verified_at']->toImmutable())
                    : CarbonImmutable::parse($data['email_verified_at']))
                : null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'email' => $this->address,
            'email_verified_at' => $this->verifiedAt,
        ];
    }

    /**
     * Check if the email is verified
     */
    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    /**
     * Get the domain part of the email address
     */
    public function getDomain(): string
    {
        return substr($this->address, strpos($this->address, '@') + 1);
    }

    /**
     * Get the local part of the email address (before the @)
     */
    public function getLocalPart(): string
    {
        return substr($this->address, 0, strpos($this->address, '@'));
    }

    /**
     * Create a new Email instance with verified status
     */
    public function withVerification(CarbonInterface $verifiedAt = null): self
    {
        $verifiedAtImmutable = $verifiedAt instanceof CarbonImmutable
            ? $verifiedAt
            : ($verifiedAt ? $verifiedAt->toImmutable() : CarbonImmutable::now());

        return new self(
            address: $this->address,
            verifiedAt: $verifiedAtImmutable
        );
    }

    /**
     * Create a new Email instance without verified status
     */
    public function withoutVerification(): self
    {
        return new self(
            address: $this->address,
            verifiedAt: null
        );
    }
}
