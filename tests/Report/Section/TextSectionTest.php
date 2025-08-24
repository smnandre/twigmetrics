<?php

declare(strict_types=1);

namespace TwigMetrics\Tests\Report\Section;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TwigMetrics\Report\Section\ReportSection;
use TwigMetrics\Report\Section\TextSection;

#[CoversClass(TextSection::class)]
class TextSectionTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $title = 'Summary';
        $content = 'This is a summary of the analysis results.';

        $section = new TextSection($title, $content);

        $this->assertSame($title, $section->getTitle());
        $this->assertSame('text', $section->getType());
        $this->assertSame($content, $section->getContent());
    }

    public function testInheritsFromReportSection(): void
    {
        $section = new TextSection('Test', 'Content');

        $this->assertInstanceOf(ReportSection::class, $section);
        $this->assertSame('text', $section->getType());
    }

    public function testEmptyContent(): void
    {
        $section = new TextSection('Empty', '');

        $this->assertSame('', $section->getContent());
    }

    public function testMultiLineContent(): void
    {
        $content = "Line 1\nLine 2\nLine 3";
        $section = new TextSection('Multi-line', $content);

        $this->assertSame($content, $section->getContent());
        $this->assertStringContainsString("\n", $section->getContent());
    }

    public function testSpecialCharactersInContent(): void
    {
        $content = 'Content with special chars: <>&"\'';
        $section = new TextSection('Special', $content);

        $this->assertSame($content, $section->getContent());
    }

    public function testCanAddSubSections(): void
    {
        $section = new TextSection('Parent', 'Content');
        $subSection = new TextSection('Child', 'Sub content');

        $section->addSubSection($subSection);

        $this->assertCount(1, $section->getSubSections());
        $this->assertSame($subSection, $section->getSubSections()[0]);
    }
}
