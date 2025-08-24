<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

use Symfony\Component\Finder\SplFileInfo;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class AnalysisResult
{
    /**
     * @param array<string, mixed> $metrics
     */
    public function __construct(
        public \SplFileInfo $file,
        public array $metrics,
        public float $analysisTime,
    ) {
    }

    public function getRelativePath(): string
    {
        if ($this->file instanceof SplFileInfo) {
            return $this->file->getRelativePathname();
        }

        return $this->file->getFilename();
    }

    public function getMetric(string $name): mixed
    {
        return $this->metrics[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->metrics;
    }
}
