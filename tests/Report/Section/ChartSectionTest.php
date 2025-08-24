<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Report\Section;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Report\Section\ChartSection;
use TwigMetrics\Report\Section\ReportSection;

#[CoversClass(ChartSection::class)]
class ChartSectionTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $chartSection = new ChartSection(
            'My Chart',
            'bar',
            ['labels' => ['A'], 'datasets' => [1]]
        );

        $this->assertInstanceOf(ChartSection::class, $chartSection);
        $this->assertInstanceOf(ReportSection::class, $chartSection);
    }

    public function testGettersReturnCorrectValues(): void
    {
        $title = 'My Awesome Chart';
        $chartType = 'bar';
        $chartData = [
            'labels' => ['Jan', 'Feb', 'Mar'],
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => [12, 19, 3],
                ],
            ],
        ];
        $options = ['responsive' => true];

        $chartSection = new ChartSection($title, $chartType, $chartData, $options);

        $this->assertSame($title, $chartSection->getTitle());
        $this->assertSame('chart', $chartSection->getType());
        $this->assertSame($options, $chartSection->getMetadata());

        $expectedData = [
            'chart_type' => $chartType,
            'chart_data' => $chartData,
        ];
        $this->assertSame($expectedData, $chartSection->getData());

        $this->assertSame($chartType, $chartSection->getChartType());
        $this->assertSame($chartData, $chartSection->getChartData());
    }

    public function testConstructorWithDefaultOptions(): void
    {
        $title = 'Another Chart';
        $chartType = 'line';
        $chartData = ['data' => [1, 2, 3]];

        $chartSection = new ChartSection($title, $chartType, $chartData);

        $this->assertSame($title, $chartSection->getTitle());
        $this->assertSame('line', $chartSection->getChartType());
        $this->assertSame($chartData, $chartSection->getChartData());
        $this->assertEmpty($chartSection->getMetadata());
        $this->assertEmpty($chartSection->getSubSections());
    }
}
