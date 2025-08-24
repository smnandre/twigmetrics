<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\ReportSection;

#[CoversClass(Report::class)]
class ReportTest extends TestCase
{
    public function testConstructorAndBasicGetters(): void
    {
        $title = 'Test Report';
        $report = new Report($title);

        $this->assertSame($title, $report->getTitle());
        $this->assertIsArray($report->getSections());
        $this->assertEmpty($report->getSections());
    }

    public function testConstructorWithInitialSections(): void
    {
        $title = 'Test Report';
        $section1 = new ReportSection('Section 1', 'text');
        $section2 = new ReportSection('Section 2', 'table');
        $sections = [$section1, $section2];

        $report = new Report($title, $sections);

        $this->assertSame($title, $report->getTitle());
        $this->assertCount(2, $report->getSections());
        $this->assertSame($section1, $report->getSections()[0]);
        $this->assertSame($section2, $report->getSections()[1]);
    }

    public function testAddSection(): void
    {
        $report = new Report('Test Report');
        $section = new ReportSection('New Section', 'text');

        $this->assertEmpty($report->getSections());

        $report->addSection($section);

        $this->assertCount(1, $report->getSections());
        $this->assertSame($section, $report->getSections()[0]);
    }

    public function testAddMultipleSections(): void
    {
        $report = new Report('Test Report');
        $section1 = new ReportSection('Section 1', 'text');
        $section2 = new ReportSection('Section 2', 'table');
        $section3 = new ReportSection('Section 3', 'chart');

        $report->addSection($section1);
        $report->addSection($section2);
        $report->addSection($section3);

        $sections = $report->getSections();
        $this->assertCount(3, $sections);
        $this->assertSame($section1, $sections[0]);
        $this->assertSame($section2, $sections[1]);
        $this->assertSame($section3, $sections[2]);
    }

    public function testGetSectionsReturnsArray(): void
    {
        $report = new Report('Test Report');

        $this->assertIsArray($report->getSections());
    }
}
