<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Widgets;

use PHPUnit\Framework\TestCase;

final class CostChartInteractivityPluginsTest extends TestCase
{
    public function testItIncludesCurrentPeriodAnnotationConfig(): void
    {
        $widget = new CostChartTestWidget();

        $options = $widget->callGetOptions();

        $this->assertArrayHasKey('plugins', $options);
        $this->assertArrayHasKey('annotation', $options['plugins']);
        $this->assertArrayHasKey('annotations', $options['plugins']['annotation']);
        $this->assertArrayHasKey('currentPeriod', $options['plugins']['annotation']['annotations']);

        $current = $options['plugins']['annotation']['annotations']['currentPeriod'];
        $this->assertIsArray($current);
        $this->assertArrayHasKey('type', $current);
        $this->assertSame('line', $current['type']);
        // Accept either Chart.js annotation value forms: xValue, value (with scaleID), or xMin/xMax
        $hasXValue = array_key_exists('xValue', $current);
        $hasValue = array_key_exists('value', $current);
        $hasXMinMax = array_key_exists('xMin', $current) && array_key_exists('xMax', $current);
        $this->assertTrue(
            $hasXValue || $hasValue || $hasXMinMax,
            'Annotation must have either xValue, value, or xMin/xMax'
        );
        if ($hasXValue) {
            $this->assertIsString($current['xValue']);
        } elseif ($hasValue) {
            $this->assertIsString($current['value']);
        } else {
            $this->assertIsString($current['xMin']);
            $this->assertIsString($current['xMax']);
        }
    }

    public function testItIncludesZoomAndPanConfig(): void
    {
        $widget = new CostChartTestWidget();

        $options = $widget->callGetOptions();

        $this->assertArrayHasKey('plugins', $options);
        $this->assertArrayHasKey('zoom', $options['plugins']);

        $zoom = $options['plugins']['zoom'];

        $this->assertArrayHasKey('zoom', $zoom);
        $this->assertArrayHasKey('wheel', $zoom['zoom']);
        $this->assertArrayHasKey('enabled', $zoom['zoom']['wheel']);
        $this->assertTrue($zoom['zoom']['wheel']['enabled']);

        $this->assertArrayHasKey('pinch', $zoom['zoom']);
        $this->assertArrayHasKey('enabled', $zoom['zoom']['pinch']);
        $this->assertTrue($zoom['zoom']['pinch']['enabled']);

        $this->assertArrayHasKey('mode', $zoom['zoom']);
        $this->assertSame('x', $zoom['zoom']['mode']);

        $this->assertArrayHasKey('pan', $zoom);
        $this->assertArrayHasKey('enabled', $zoom['pan']);
        $this->assertTrue($zoom['pan']['enabled']);
        $this->assertArrayHasKey('mode', $zoom['pan']);
        $this->assertSame('x', $zoom['pan']['mode']);
    }
}
