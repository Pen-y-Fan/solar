<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Support\Actions\ActionResult;
use PHPUnit\Framework\TestCase;

class ActionResultTest extends TestCase
{
    public function testSuccessResultHelpers(): void
    {
        $result = ActionResult::success(['foo' => 'bar'], 'ok');
        $this->assertTrue($result->isSuccess());
        $this->assertSame('ok', $result->getMessage());
        $this->assertSame(['foo' => 'bar'], $result->getData());
        $this->assertNull($result->getCode());
    }

    public function testFailureResultHelpers(): void
    {
        $result = ActionResult::failure('bad', 'E123');
        $this->assertFalse($result->isSuccess());
        $this->assertSame('bad', $result->getMessage());
        $this->assertNull($result->getData());
        $this->assertSame('E123', $result->getCode());
    }
}
