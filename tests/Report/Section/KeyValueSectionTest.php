<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Report\Section;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Report\Section\KeyValueSection;
use TwigMetrics\Report\Section\ReportSection;

#[CoversClass(KeyValueSection::class)]
class KeyValueSectionTest extends TestCase
{
    public function testInstantiationAndGetters(): void
    {
        $title = 'Test Section';
        $pairs = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $section = new KeyValueSection($title, $pairs);

        $this->assertInstanceOf(ReportSection::class, $section);
        $this->assertSame($title, $section->getTitle());
        $this->assertSame('keyvalue', $section->getType());
        $this->assertSame($pairs, $section->getPairs());
        $this->assertSame(['pairs' => $pairs], $section->getData());
        $this->assertEmpty($section->getMetadata());
    }

    public function testInstantiationWithOptions(): void
    {
        $title = 'Section With Options';
        $pairs = ['foo' => 'bar'];
        $options = ['option1' => 'value1', 'class' => 'text-danger'];

        $section = new KeyValueSection($title, $pairs, $options);

        $this->assertSame($options, $section->getMetadata());
    }

    public function testGetPairsWithEmptyArray(): void
    {
        $section = new KeyValueSection('Empty Section', []);

        $this->assertSame([], $section->getPairs());
        $this->assertSame(['pairs' => []], $section->getData());
    }
}
