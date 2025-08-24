<?php

declare(strict_types=1);

namespace TwigMetrics\Report\Section;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
class ReportSection
{
    /**
     * @param ReportSection[]      $subSections
     * @param array<string, mixed> $data
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private string $title,
        private string $type,
        private array $data = [],
        private array $metadata = [],
        private array $subSections = [],
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return ReportSection[]
     */
    public function getSubSections(): array
    {
        return $this->subSections;
    }

    public function addSubSection(ReportSection $section): void
    {
        $this->subSections[] = $section;
    }
}
