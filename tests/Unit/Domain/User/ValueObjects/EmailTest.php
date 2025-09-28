<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\User\ValueObjects;

use App\Domain\User\ValueObjects\Email;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class EmailTest extends TestCase
{
    public function testConstructWithValidEmailSetsProperties(): void
    {
        $verifiedAt = CarbonImmutable::parse('2024-01-02 03:04:05');
        $email = new Email('User+tag@Example.COM', $verifiedAt);

        $this->assertSame('User+tag@Example.COM', $email->address);
        $this->assertSame($verifiedAt, $email->verifiedAt);
        $this->assertTrue($email->isVerified());
        $this->assertSame('Example.COM', $email->getDomain());
        $this->assertSame('User+tag', $email->getLocalPart());
    }

    public function testConstructWithInvalidEmailThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');
        new Email('not-an-email');
    }

    public function testFromArrayAcceptsStringsAndCarbonVariantsForVerifiedAt(): void
    {
        $cases = [
            ['email' => 'a@b.com', 'email_verified_at' => null],
            ['email' => 'a@b.com', 'email_verified_at' => '2024-02-03 04:05:06'],
            ['email' => 'a@b.com', 'email_verified_at' => Carbon::parse('2024-02-03 04:05:06')],
            ['email' => 'a@b.com', 'email_verified_at' => CarbonImmutable::parse('2024-02-03 04:05:06')],
        ];

        foreach ($cases as $data) {
            $email = Email::fromArray($data);
            $this->assertSame($data['email'], $email->address);

            if ($data['email_verified_at'] === null) {
                $this->assertNull($email->verifiedAt);
                $this->assertFalse($email->isVerified());
            } else {
                $this->assertInstanceOf(CarbonImmutable::class, $email->verifiedAt);
                $this->assertTrue($email->isVerified());
            }

            // Round trip array conversion
            $array = $email->toArray();
            $this->assertSame($data['email'], $array['email']);
            $this->assertSame($email->verifiedAt, $array['email_verified_at']);
        }
    }

    public function testWithVerificationUsesNowWhenNotProvidedAndConvertsMutableToImmutable(): void
    {
        CarbonImmutable::setTestNow('2024-05-06 07:08:09');
        $email = new Email('test@example.com');

        $verifiedNow = $email->withVerification();
        $this->assertInstanceOf(CarbonImmutable::class, $verifiedNow->verifiedAt);
        $this->assertSame('2024-05-06 07:08:09', $verifiedNow->verifiedAt->format('Y-m-d H:i:s'));

        $mutable = Carbon::parse('2024-01-01 00:00:00');
        $verifiedFromMutable = $email->withVerification($mutable);
        $this->assertInstanceOf(CarbonImmutable::class, $verifiedFromMutable->verifiedAt);
        $this->assertSame('2024-01-01 00:00:00', $verifiedFromMutable->verifiedAt->format('Y-m-d H:i:s'));

        CarbonImmutable::setTestNow(); // clear
    }

    public function testWithoutVerificationClearsVerifiedAt(): void
    {
        $email = new Email('test@example.com', CarbonImmutable::now());
        $cleared = $email->withoutVerification();

        $this->assertNull($cleared->verifiedAt);
        $this->assertFalse($cleared->isVerified());
        // original instance unchanged
        $this->assertNotNull($email->verifiedAt);
    }
}
