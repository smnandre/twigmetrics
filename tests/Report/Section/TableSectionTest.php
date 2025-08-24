<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Report\Section;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Report\Section\ReportSection;
use TwigMetrics\Report\Section\TableSection;

#[CoversClass(TableSection::class)]
class TableSectionTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $title = 'Template Analysis';
        $headers = ['Template', 'Lines', 'Complexity'];
        $rows = [
            ['template1.twig', 50, 8],
            ['template2.twig', 75, 12],
        ];

        $section = new TableSection($title, $headers, $rows);

        $this->assertSame($title, $section->getTitle());
        $this->assertSame('table', $section->getType());
        $this->assertSame($headers, $section->getHeaders());
        $this->assertSame($rows, $section->getRows());
    }

    public function testConstructorWithOptions(): void
    {
        $title = 'Templates';
        $headers = ['Name', 'Size'];
        $rows = [['test.twig', 100]];
        $options = ['sortable' => true, 'theme' => 'dark'];

        $section = new TableSection($title, $headers, $rows, $options);

        $this->assertEquals($options, $section->getMetadata());
        $this->assertTrue($section->getMetadata()['sortable']);
        $this->assertEquals('dark', $section->getMetadata()['theme']);
    }

    public function testInheritsFromReportSection(): void
    {
        $section = new TableSection('Test', [], []);

        $this->assertInstanceOf(ReportSection::class, $section);
        $this->assertSame('table', $section->getType());
    }

    public function testEmptyTable(): void
    {
        $section = new TableSection('Empty Table', [], []);

        $this->assertEmpty($section->getHeaders());
        $this->assertEmpty($section->getRows());
    }

    public function testDataStructure(): void
    {
        $headers = ['Col1', 'Col2'];
        $rows = [['A', 'B'], ['C', 'D']];
        $section = new TableSection('Test', $headers, $rows);

        $data = $section->getData();
        $this->assertArrayHasKey('headers', $data);
        $this->assertArrayHasKey('rows', $data);
        $this->assertEquals($headers, $data['headers']);
        $this->assertEquals($rows, $data['rows']);
    }

    public function testSingleRowTable(): void
    {
        $headers = ['Template', 'Complexity'];
        $rows = [['single.twig', 5]];
        $section = new TableSection('Single Row', $headers, $rows);

        $this->assertCount(2, $section->getHeaders());
        $this->assertCount(1, $section->getRows());
        $this->assertEquals(['single.twig', 5], $section->getRows()[0]);
    }

    public function testCanAddSubSections(): void
    {
        $section = new TableSection('Parent', ['Col1'], [['Data1']]);
        $subSection = new TableSection('Child', ['Col2'], [['Data2']]);

        $section->addSubSection($subSection);

        $this->assertCount(1, $section->getSubSections());
        $this->assertSame($subSection, $section->getSubSections()[0]);
    }
}
