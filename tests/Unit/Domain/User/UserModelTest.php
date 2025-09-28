<?php

namespace Tests\Unit\Domain\User;

use App\Domain\User\Models\User;
use App\Domain\User\ValueObjects\Email;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    public function testGetEmailValueObjectRoundTrip(): void
    {
        $user = new User();
        $verifiedAt = CarbonImmutable::parse('2025-01-02 03:04:05');
        // set raw attributes the same way Eloquent would hydrate
        $user->forceFill([
            'email' => 'Test.User+tag@example.com',
            'email_verified_at' => $verifiedAt,
        ]);

        $emailVO = $user->getEmailValueObject();

        $this->assertInstanceOf(Email::class, $emailVO);
        $this->assertSame('Test.User+tag@example.com', $emailVO->address);
        $this->assertTrue($emailVO->isVerified());
        $this->assertInstanceOf(CarbonImmutable::class, $emailVO->verifiedAt);
        $this->assertTrue($verifiedAt->eq($emailVO->verifiedAt));
        $this->assertSame('example.com', $emailVO->getDomain());
        $this->assertSame('Test.User+tag', $emailVO->getLocalPart());

        // Ensure model accessor also reflects VO
        $this->assertSame('Test.User+tag@example.com', $user->email);
    }

    public function testSetEmailAttributeUpdatesVoAndRawAttributes(): void
    {
        $user = new User();
        $verifiedAt = CarbonImmutable::parse('2024-12-31 23:59:59');
        $user->forceFill(['email_verified_at' => $verifiedAt]);

        $user->email = 'person@example.org';

        // Raw attribute set
        $this->assertSame('person@example.org', $user->getAttributes()['email']);
        // Accessor uses VO
        $this->assertSame('person@example.org', $user->email);

        $emailVO = $user->getEmailValueObject();
        $this->assertSame('person@example.org', $emailVO->address);
        $this->assertTrue($emailVO->isVerified());
        $this->assertTrue($verifiedAt->eq($emailVO->verifiedAt));
    }

    public function testSetEmailVerifiedAtAttributeUpdatesVo(): void
    {
        $user = new User();
        $user->email = 'alpha@example.net';

        // Initially unverified
        $this->assertFalse($user->getEmailValueObject()->isVerified());

        $verifiedAt = CarbonImmutable::parse('2025-02-02 10:00:00');
        $user->email_verified_at = $verifiedAt;

        $emailVO = $user->getEmailValueObject();
        $this->assertTrue($emailVO->isVerified());
        $this->assertTrue($verifiedAt->eq($emailVO->verifiedAt));

        // Clear verification and ensure VO follows
        $user->email_verified_at = null;
        $this->assertFalse($user->getEmailValueObject()->isVerified());
        $this->assertNull($user->getEmailValueObject()->verifiedAt);
    }

    public function testInvalidEmailThrowsExceptionOnSetter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        $user = new User();
        $user->email = 'not-an-email';
    }
}
