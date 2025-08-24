<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Report\Section;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Report\Section\ListSection;
use TwigMetrics\Report\Section\ReportSection;

#[CoversClass(ListSection::class)]
class ListSectionTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $title = 'Issues';
        $items = ['Issue 1', 'Issue 2', 'Issue 3'];

        $section = new ListSection($title, $items);

        $this->assertSame($title, $section->getTitle());
        $this->assertSame('list', $section->getType());
        $this->assertSame($items, $section->getItems());
        $this->assertSame('bullet', $section->getListType());
    }

    public function testConstructorWithCustomListType(): void
    {
        $title = 'Steps';
        $items = ['Step 1', 'Step 2', 'Step 3'];
        $listType = 'numbered';

        $section = new ListSection($title, $items, $listType);

        $this->assertSame($title, $section->getTitle());
        $this->assertSame($items, $section->getItems());
        $this->assertSame($listType, $section->getListType());
    }

    public function testInheritsFromReportSection(): void
    {
        $section = new ListSection('Test', []);

        $this->assertInstanceOf(ReportSection::class, $section);
        $this->assertSame('list', $section->getType());
    }

    public function testEmptyList(): void
    {
        $section = new ListSection('Empty', []);

        $this->assertEmpty($section->getItems());
        $this->assertSame('bullet', $section->getListType());
    }

    public function testDifferentListTypes(): void
    {
        $bulletList = new ListSection('Bullets', ['A', 'B'], 'bullet');
        $numberedList = new ListSection('Numbers', ['1', '2'], 'numbered');
        $plainList = new ListSection('Plain', ['X', 'Y'], 'plain');

        $this->assertSame('bullet', $bulletList->getListType());
        $this->assertSame('numbered', $numberedList->getListType());
        $this->assertSame('plain', $plainList->getListType());
    }

    public function testDataStructure(): void
    {
        $items = ['Item A', 'Item B'];
        $section = new ListSection('Test', $items, 'numbered');

        $data = $section->getData();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('list_type', $data);
        $this->assertEquals($items, $data['items']);
        $this->assertEquals('numbered', $data['list_type']);
    }

    public function testCanAddSubSections(): void
    {
        $section = new ListSection('Parent', ['Item 1']);
        $subSection = new ListSection('Child', ['Sub item']);

        $section->addSubSection($subSection);

        $this->assertCount(1, $section->getSubSections());
        $this->assertSame($subSection, $section->getSubSections()[0]);
    }
}
