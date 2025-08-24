<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Report\Section;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Report\Section\ReportSection;

#[CoversClass(ReportSection::class)]
class ReportSectionTest extends TestCase
{
    public function testConstructorAndBasicGetters(): void
    {
        $title = 'Test Section';
        $type = 'text';
        $section = new ReportSection($title, $type);

        $this->assertSame($title, $section->getTitle());
        $this->assertSame($type, $section->getType());
        $this->assertIsArray($section->getData());
        $this->assertEmpty($section->getData());
        $this->assertIsArray($section->getMetadata());
        $this->assertEmpty($section->getMetadata());
        $this->assertIsArray($section->getSubSections());
        $this->assertEmpty($section->getSubSections());
    }

    public function testConstructorWithAllParameters(): void
    {
        $title = 'Test Section';
        $type = 'table';
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $metadata = ['author' => 'test', 'version' => '1.0'];
        $subSection = new ReportSection('Sub Section', 'text');
        $subSections = [$subSection];

        $section = new ReportSection($title, $type, $data, $metadata, $subSections);

        $this->assertSame($title, $section->getTitle());
        $this->assertSame($type, $section->getType());
        $this->assertSame($data, $section->getData());
        $this->assertSame($metadata, $section->getMetadata());
        $this->assertCount(1, $section->getSubSections());
        $this->assertSame($subSection, $section->getSubSections()[0]);
    }

    public function testAddSubSection(): void
    {
        $section = new ReportSection('Parent Section', 'text');
        $subSection = new ReportSection('Sub Section', 'table');

        $this->assertEmpty($section->getSubSections());

        $section->addSubSection($subSection);

        $this->assertCount(1, $section->getSubSections());
        $this->assertSame($subSection, $section->getSubSections()[0]);
    }

    public function testAddMultipleSubSections(): void
    {
        $section = new ReportSection('Parent Section', 'text');
        $subSection1 = new ReportSection('Sub Section 1', 'table');
        $subSection2 = new ReportSection('Sub Section 2', 'chart');
        $subSection3 = new ReportSection('Sub Section 3', 'list');

        $section->addSubSection($subSection1);
        $section->addSubSection($subSection2);
        $section->addSubSection($subSection3);

        $subSections = $section->getSubSections();
        $this->assertCount(3, $subSections);
        $this->assertSame($subSection1, $subSections[0]);
        $this->assertSame($subSection2, $subSections[1]);
        $this->assertSame($subSection3, $subSections[2]);
    }

    public function testDifferentSectionTypes(): void
    {
        $textSection = new ReportSection('Text', 'text');
        $tableSection = new ReportSection('Table', 'table');
        $chartSection = new ReportSection('Chart', 'chart');
        $listSection = new ReportSection('List', 'list');

        $this->assertSame('text', $textSection->getType());
        $this->assertSame('table', $tableSection->getType());
        $this->assertSame('chart', $chartSection->getType());
        $this->assertSame('list', $listSection->getType());
    }

    public function testDataAndMetadataHandling(): void
    {
        $data = [
            'templates' => 15,
            'complexity' => 8.5,
            'issues' => ['warning1', 'error1'],
        ];
        $metadata = [
            'generated_at' => '2023-01-01',
            'tool_version' => '1.0.0',
        ];

        $section = new ReportSection('Metrics', 'table', $data, $metadata);

        $this->assertSame($data, $section->getData());
        $this->assertSame($metadata, $section->getMetadata());
        $this->assertSame(15, $section->getData()['templates']);
        $this->assertSame('1.0.0', $section->getMetadata()['tool_version']);
    }
}
