<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

use Symfony\Component\Finder\SplFileInfo;
use Twig\Source;
use TwigMetrics\Collector\CodeStyleCollector;
use TwigMetrics\Collector\CollectorInterface;
use TwigMetrics\Collector\RelationshipCollector;
use TwigMetrics\Template\Parser\AstTraverser;
use TwigMetrics\Template\Parser\TwigSourceParser;

/**
 * Analyzes individual Twig templates using collectors.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class TemplateAnalyzer
{
    /**
     * @param CollectorInterface[] $collectors
     */
    public function __construct(
        private array $collectors,
        private AstTraverser $traverser,
        private TwigSourceParser $parser,
    ) {
    }

    public function analyze(\SplFileInfo $file): AnalysisResult
    {
        $startTime = microtime(true);

        $this->setCollectorContext($file);

        if (!is_readable($file->getPathname())) {
            throw new \RuntimeException('Unable to read file: '.$file->getPathname());
        }

        \assert(method_exists($file, 'getRelativePathname'));
        $source = new Source(file_get_contents($file->getPathname()), $file->getRelativePathname());

        $sourceCollectors = [];
        $astCollectors = [];
        foreach ($this->collectors as $collector) {
            if ($collector instanceof CodeStyleCollector) {
                $sourceCollectors[] = $collector;
            } else {
                $astCollectors[] = $collector;
            }
        }

        $sourceMetrics = [];
        foreach ($sourceCollectors as $collector) {
            $sourceMetrics = [...$sourceMetrics, ...$collector->collect($source)];
        }

        $astMetrics = [];
        if (!empty($astCollectors)) {
            try {
                $ast = $this->parser->parseFile($file);
                $this->traverser->traverse($ast, $astCollectors);
                $astMetrics = $this->aggregateMetrics($astCollectors);
            } catch (\Exception $e) {
                $astMetrics = [
                    'parsing_error' => 'Unexpected parsing error: '.$e->getMessage(),
                    'functions' => 0,
                    'filters' => 0,
                    'includes' => 0,
                    'extends' => 0,
                    'conditions' => 0,
                    'loops' => 0,
                    'complexity_score' => 0,
                    'max_depth' => 0,
                    'blocks' => 0,
                ];
            }
        }

        $allMetrics = array_merge($astMetrics, $sourceMetrics);
        $enhancedMetrics = $this->enhanceMetrics($allMetrics, $file);

        $analysisTime = microtime(true) - $startTime;

        return new AnalysisResult($file, $enhancedMetrics, $analysisTime);
    }

    private function setCollectorContext(\SplFileInfo $file): void
    {
        foreach ($this->collectors as $collector) {
            if ($collector instanceof RelationshipCollector) {
                $relativePath = $file instanceof SplFileInfo ? $file->getRelativePathname() : $file->getFilename();
                $collector->setCurrentTemplate($relativePath);
            }
        }
    }

    /**
     * @param CollectorInterface[] $collectors
     *
     * @return array<string, mixed>
     */
    private function aggregateMetrics(array $collectors): array
    {
        $metrics = [];
        foreach ($collectors as $collector) {
            $metrics = array_merge($metrics, $collector->getData());
        }

        return $metrics;
    }

    /**
     * @param array<string, mixed> $metrics
     *
     * @return array<string, mixed>
     */
    private function enhanceMetrics(array $metrics, \SplFileInfo $file): array
    {
        $lines = $metrics['lines'] ?? 0;
        $complexity = $metrics['complexity_score'] ?? 0;
        $depth = $metrics['max_depth'] ?? 0;

        $relativePath = $file instanceof SplFileInfo ? $file->getRelativePathname() : $file->getFilename();

        return array_merge($metrics, [
            'file_size_bytes' => $file->getSize(),
            'file_category' => $this->categorizeTemplate($relativePath),
            'file_extension' => $file->getExtension(),
            'complexity_per_line' => $this->calculateComplexityPerLine($metrics),
            'function_density' => $this->calculateFunctionDensity($metrics),
            'size_rating' => TemplateRating::calculateSizeRating($lines),
            'complexity_rating' => TemplateRating::calculateComplexityRating($complexity, $depth),
            'callables_rating' => TemplateRating::calculateCallablesRating($metrics),
        ]);
    }

    private function categorizeTemplate(string $path): string
    {
        $directory = dirname($path);

        return match (true) {
            str_contains($directory, 'component') => 'component',
            str_contains($directory, 'page') => 'page',
            str_contains($directory, 'layout') => 'layout',
            str_contains($directory, 'form') => 'form',
            str_contains($directory, 'email') => 'email',
            str_contains($directory, 'admin') => 'admin',
            '.' === $directory => 'root',
            default => 'other',
        };
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function calculateComplexityPerLine(array $metrics): float
    {
        $lines = $metrics['lines'] ?? 1;
        $complexity = $metrics['complexity_score'] ?? 0;

        return $lines > 0 ? round($complexity / $lines, 2) : 0;
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function calculateFunctionDensity(array $metrics): float
    {
        $lines = $metrics['lines'] ?? 1;
        $functions = $metrics['functions'] ?? 0;

        return $lines > 0 ? round($functions / $lines * 100, 1) : 0;
    }
}
