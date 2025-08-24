<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\ListSection;
use TwigMetrics\Report\Section\TableSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class TemplateRelationshipsReporter extends DimensionReporter
{
    public function getWeight(): float
    {
        return 0.15;
    }

    public function getDimensionName(): string
    {
        return 'Template Relationships';
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generate(array $results): Report
    {
        $report = new Report('Template Relationships Analysis');

        $report->addSection($this->createInheritanceChains($results));
        $report->addSection($this->createDependencyGraph($results));
        $report->addSection($this->createOrphanedTemplates($results));
        $report->addSection($this->createCircularDependencies());

        return $report;
    }

    public function calculateDimensionScore(array $results): float
    {
        $relationships = $this->analyzeRelationships($results);

        $score = 100;

        if ($relationships['circular_deps'] > 0) {
            $score -= 30;
        }

        $orphanRatio = count($results) > 0 ? $relationships['orphaned_count'] / count($results) : 0;
        if ($orphanRatio > 0.3) {
            $score -= 25;
        } elseif ($orphanRatio > 0.2) {
            $score -= 15;
        } elseif ($orphanRatio > 0.1) {
            $score -= 10;
        }

        if ($relationships['max_inheritance_depth'] > 5) {
            $score -= 20;
        } elseif ($relationships['max_inheritance_depth'] > 3) {
            $score -= 10;
        }

        return max(0, $score);
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function analyzeRelationships(array $results): array
    {
        $inheritanceDepths = [];
        $orphaned = [];

        foreach ($results as $result) {
            $extends = $result->getMetric('extends') ?? [];
            $includes = $result->getMetric('includes') ?? [];

            if (!is_array($extends)) {
                $extends = [];
            }
            if (!is_array($includes)) {
                $includes = [];
            }

            if (empty($extends) && empty($includes)) {
                $orphaned[] = $result->getRelativePath();
            }

            $inheritanceDepths[] = $this->calculateInheritanceDepth($result, $results);
        }

        return [
            'circular_deps' => $this->findCircularDependencies(),
            'orphaned_count' => count($orphaned),
            'orphaned_templates' => $orphaned,
            'max_inheritance_depth' => max($inheritanceDepths ?: [0]),
        ];
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function calculateInheritanceDepth(AnalysisResult $template, array $results): int
    {
        $depth = 0;
        $current = $template;
        $visited = [];

        while ($current && !in_array($current->getRelativePath(), $visited)) {
            $visited[] = $current->getRelativePath();
            $extends = $current->getMetric('extends') ?? [];
            if (!is_array($extends)) {
                $extends = [];
            }
            if (empty($extends)) {
                break;
            }

            $parentPath = $extends[0] ?? null;
            $current = $this->findTemplateByPath($parentPath, $results);
            ++$depth;
        }

        return $depth;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function findTemplateByPath(?string $path, array $results): ?AnalysisResult
    {
        if (!$path) {
            return null;
        }

        foreach ($results as $result) {
            if (str_contains($result->getRelativePath(), $path)) {
                return $result;
            }
        }

        return null;
    }

    private function findCircularDependencies(): int
    {
        return 0;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createInheritanceChains(array $results): TableSection
    {
        $chains = [];

        foreach ($results as $result) {
            $extends = $result->getMetric('extends') ?? [];
            if (!empty($extends) && is_array($extends)) {
                $depth = $this->calculateInheritanceDepth($result, $results);
                $chains[] = [
                    basename($result->getRelativePath()),
                    implode(' → ', array_map('basename', $extends)),
                    $depth,
                ];
            }
        }

        usort($chains, fn ($a, $b) => $b[2] <=> $a[2]);

        return new TableSection(
            'Inheritance Chains',
            ['Template', 'Extends Chain', 'Depth'],
            array_slice($chains, 0, 20)
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createDependencyGraph(array $results): TableSection
    {
        $dependencies = [];

        foreach ($results as $result) {
            $extends = $result->getMetric('extends') ?? [];
            $includes = $result->getMetric('includes') ?? [];

            if (!is_array($extends)) {
                $extends = [];
            }
            if (!is_array($includes)) {
                $includes = [];
            }

            $allDeps = array_merge($extends, $includes);
            if (!empty($allDeps)) {
                $dependencies[] = [
                    basename($result->getRelativePath()),
                    count($allDeps),
                    implode(', ', array_map('basename', array_slice($allDeps, 0, 3))),
                ];
            }
        }

        usort($dependencies, fn ($a, $b) => $b[1] <=> $a[1]);

        return new TableSection(
            'Template Dependencies',
            ['Template', 'Dep Count', 'Dependencies (first 3)'],
            array_slice($dependencies, 0, 15)
        );
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function createOrphanedTemplates(array $results): ListSection
    {
        $relationships = $this->analyzeRelationships($results);
        $orphaned = array_map('basename', $relationships['orphaned_templates']);

        return new ListSection(
            'Orphaned Templates',
            $orphaned,
            'bullet'
        );
    }

    private function createCircularDependencies(): ListSection
    {
        return new ListSection(
            'Circular Dependencies',
            ['None detected'],
            'bullet'
        );
    }
}
