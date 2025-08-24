<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class BatchAnalysisResult
{
    /**
     * @param AnalysisResult[]                         $templateResults
     * @param array<string, list<string>>              $dependencyGraph
     * @param list<array{file: string, error: string}> $errors
     */
    public function __construct(
        public array $templateResults,
        public array $dependencyGraph,
        public float $analysisTime,
        private array $errors = [],
    ) {
    }

    /**
     * @return AnalysisResult[]
     */
    public function getTemplateResults(): array
    {
        return $this->templateResults;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getDependencyGraph(): array
    {
        return $this->dependencyGraph;
    }

    /**
     * @return list<array{file: string, error: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArchitecturalInsights(): array
    {
        $referenceCounts = $this->calculateReferenceCounts();
        arsort($referenceCounts);
        $mostReferenced = array_slice($referenceCounts, 0, 5, true);

        $orphaned = array_filter($referenceCounts, fn ($count) => 0 === $count);

        $circularDeps = $this->findCircularDependencies();

        $categories = $this->analyzeCategoryDistribution();

        $complexityByCategory = $this->analyzeComplexityByCategory();

        return [
            'most_referenced' => $mostReferenced,
            'orphaned_templates' => array_keys($orphaned),
            'circular_dependencies' => $circularDeps,
            'category_distribution' => $categories,
            'complexity_by_category' => $complexityByCategory,
            'architectural_health_score' => $this->calculateArchitecturalHealthScore(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPerformanceInsights(): array
    {
        $totalTemplates = count($this->templateResults);
        $avgAnalysisTime = $totalTemplates > 0 ? $this->analysisTime / $totalTemplates : 0;

        $slowestTemplates = [];
        foreach ($this->templateResults as $result) {
            if ($result->analysisTime > $avgAnalysisTime * 2) {
                $slowestTemplates[] = [
                    'template' => $result->getRelativePath(),
                    'time' => $result->analysisTime,
                ];
            }
        }

        return [
            'total_analysis_time' => $this->analysisTime,
            'average_per_template' => $avgAnalysisTime,
            'slowest_templates' => $slowestTemplates,
            'templates_per_second' => $this->analysisTime > 0 ? $totalTemplates / $this->analysisTime : 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function calculateReferenceCounts(): array
    {
        $counts = [];

        foreach ($this->templateResults as $result) {
            $counts[$result->getRelativePath()] = 0;
        }

        foreach ($this->dependencyGraph as $dependencies) {
            foreach ($dependencies as $dependency) {
                if (isset($counts[$dependency])) {
                    ++$counts[$dependency];
                }
            }
        }

        return $counts;
    }

    /**
     * @return list<array{string, string}>
     */
    private function findCircularDependencies(): array
    {
        /** @var list<array{string, string}> $circular */
        $circular = [];
        $visited = [];
        $stack = [];

        foreach (array_keys($this->dependencyGraph) as $template) {
            if (!isset($visited[$template])) {
                $this->detectCycle($template, $visited, $stack, $circular);
            }
        }

        return $circular;
    }

    /**
     * @param array<string, bool>         $visited
     * @param array<string, bool>         $stack
     * @param list<array{string, string}> $circular
     *
     * @param-out list<array{string, string}>  $circular
     */
    private function detectCycle(string $template, array &$visited, array &$stack, array &$circular): void
    {
        $visited[$template] = true;
        $stack[$template] = true;

        $dependencies = $this->dependencyGraph[$template] ?? [];
        foreach ($dependencies as $dependency) {
            if (!isset($visited[$dependency])) {
                $this->detectCycle($dependency, $visited, $stack, $circular);
            } elseif (isset($stack[$dependency]) && $stack[$dependency]) {
                $circular[] = [$template, $dependency];
            }
        }

        $stack[$template] = false;
    }

    /**
     * @return array<string, int>
     */
    private function analyzeCategoryDistribution(): array
    {
        $distribution = [];

        foreach ($this->templateResults as $result) {
            $category = $result->getMetric('file_category') ?? 'other';
            $distribution[$category] = ($distribution[$category] ?? 0) + 1;
        }

        return $distribution;
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function analyzeComplexityByCategory(): array
    {
        $complexityByCategory = [];

        foreach ($this->templateResults as $result) {
            $category = $result->getMetric('file_category') ?? 'other';
            $complexity = $result->getMetric('complexity_score') ?? 0;

            if (!isset($complexityByCategory[$category])) {
                $complexityByCategory[$category] = [];
            }

            $complexityByCategory[$category][] = $complexity;
        }

        foreach ($complexityByCategory as $category => $complexities) {
            $complexityByCategory[$category] = [
                'average' => array_sum($complexities) / count($complexities),
                'max' => max($complexities),
                'min' => min($complexities),
                'count' => count($complexities),
            ];
        }

        return $complexityByCategory;
    }

    private function calculateArchitecturalHealthScore(): int
    {
        $score = 100;
        $referenceCounts = $this->calculateReferenceCounts();

        $orphanedCount = count(array_filter($referenceCounts, fn ($count) => 0 === $count));
        $score -= $orphanedCount * 5;

        $circularDeps = $this->findCircularDependencies();
        $score -= count($circularDeps) * 15;

        $overReferenced = array_filter($referenceCounts, fn ($count) => $count > 10);
        $score -= count($overReferenced) * 10;

        $highComplexity = 0;
        foreach ($this->templateResults as $result) {
            if (($result->getMetric('complexity_score') ?? 0) > 20) {
                ++$highComplexity;
            }
        }
        $score -= $highComplexity * 8;

        return max(0, $score);
    }
}
