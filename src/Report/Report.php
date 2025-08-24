<?php

declare(strict_types=1);

namespace TwigMetrics\Report;

use TwigMetrics\Report\Section\ReportSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class Report
{
    /**
     * @param ReportSection[] $sections
     */
    public function __construct(
        private string $title,
        private array $sections = [],
    ) {
    }

    public function addSection(ReportSection $section): void
    {
        $this->sections[] = $section;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return ReportSection[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}
