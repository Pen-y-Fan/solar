<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use PHPUnit\Framework\TestCase;

final class AgileChartOptionsTest extends TestCase
{
    public function testItSetsInteractionModeToIndexAndNonIntersecting(): void
    {
        $widget = new AgileChartTestWidget();

        $options = $widget->callGetOptions();

        $this->assertArrayHasKey('interaction', $options);

        $this->assertArrayHasKey('mode', $options['interaction']);
        $this->assertSame('index', $options['interaction']['mode']);

        $this->assertArrayHasKey('intersect', $options['interaction']);
        $this->assertFalse($options['interaction']['intersect']);
    }

    public function testItIncludesTooltipCallbacksStructure(): void
    {
        $widget = new AgileChartTestWidget();

        $options = $widget->callGetOptions();

        $this->assertArrayHasKey('plugins', $options);
        $this->assertArrayHasKey('tooltip', $options['plugins']);

        // We only assert the presence/shape of callbacks configuration, not JS bodies
        $this->assertArrayHasKey('callbacks', $options['plugins']['tooltip']);

        $callbacks = $options['plugins']['tooltip']['callbacks'];
        $this->assertArrayHasKey('title', $callbacks);
        $this->assertArrayHasKey('label', $callbacks);
    }
}
