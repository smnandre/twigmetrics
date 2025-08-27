<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

/**
 * Analyzes multiple templates together for cross-template insights.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class BatchAnalyzer
{
    public function __construct(
        private TemplateAnalyzer $templateAnalyzer,
    ) {
    }

    /**
     * @param \SplFileInfo[] $files
     */
    public function analyze(array $files): BatchAnalysisResult
    {
        $startTime = microtime(true);

        $results = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                $results[] = $this->templateAnalyzer->analyze($file);
            } catch (\Exception $e) {
                assert(method_exists($file, 'getRelativePathname'));
                $errors[] = [
                    'file' => $file->getRelativePathname(),
                    'error' => $e->getMessage(),
                ];

                $fallbackMetrics = [
                    'lines' => 0,
                    'complexity_score' => 0,
                    'functions' => 0,
                    'includes' => 0,
                    'max_depth' => 0,
                    'empty_lines' => 0,
                    'comment_lines' => 0,
                    'total_line_length' => 0,
                    'total_indentation' => 0,
                    'max_line_length' => 0,
                    'max_indentation' => 0,
                    'file_size_bytes' => $file->getSize(),
                    'file_category' => 'error',
                    'file_extension' => $file->getExtension(),
                    'analysis_error' => $e->getMessage(),
                ];

                $results[] = new AnalysisResult($file, $fallbackMetrics, 0.0);
            }
        }

        $dependencyGraph = $this->buildDependencyGraph($results);

        $augmentedResults = $this->addCrossTemplateInsights($results, $dependencyGraph);

        $totalTime = microtime(true) - $startTime;

        return new BatchAnalysisResult($augmentedResults, $dependencyGraph, $totalTime, $errors);
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, array<int, string>>
     */
    private function buildDependencyGraph(array $results): array
    {
        $graph = [];

        foreach ($results as $result) {
            $templatePath = $result->getRelativePath();
            $dependencies = $result->getMetric('dependencies') ?? [];

            $graph[$templatePath] = array_map(
                fn ($dep) => $dep['template'] ?? $dep,
                is_array($dependencies) ? $dependencies : []
            );
        }

        return $graph;
    }

    /**
     * @param AnalysisResult[]                  $results
     * @param array<string, array<int, string>> $dependencyGraph
     *
     * @return AnalysisResult[]
     */
    private function addCrossTemplateInsights(array $results, array $dependencyGraph): array
    {
        $referenceCounter = $this->calculateReferenceFrequency($dependencyGraph);
        $this->analyzeBlockUsage($results);
        $architecturalRoles = $this->determineArchitecturalRoles($results, $referenceCounter);

        $augmented = [];
        foreach ($results as $result) {
            $templatePath = $result->getRelativePath();
            $metrics = $result->getData();

            $metrics['times_referenced'] = $referenceCounter[$templatePath] ?? 0;
            $metrics['popularity_rank'] = $this->calculatePopularityRank($templatePath, $referenceCounter);
            $metrics['architectural_role'] = $architecturalRoles[$templatePath] ?? 'standard';
            $metrics['coupling_risk'] = $this->calculateCouplingRisk($metrics);
            $metrics['reusability_score'] = $this->calculateReusabilityScore($metrics);

            $metrics['potential_issues'] = $this->identifyPotentialIssues($metrics);

            $augmented[] = new AnalysisResult($result->file, $metrics, $result->analysisTime);
        }

        return $augmented;
    }

    /**
     * @param array<string, list<string>> $dependencyGraph
     *
     * @return array<string, int>
     */
    private function calculateReferenceFrequency(array $dependencyGraph): array
    {
        $counter = [];

        foreach (array_keys($dependencyGraph) as $template) {
            $counter[$template] = 0;
        }

        foreach ($dependencyGraph as $dependencies) {
            foreach ($dependencies as $dependency) {
                if (isset($counter[$dependency])) {
                    ++$counter[$dependency];
                }
            }
        }

        return $counter;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, array<string, array<int, string>>>
     */
    private function analyzeBlockUsage(array $results): array
    {
        $blockDefinitions = [];
        $blockUsage = [];

        foreach ($results as $result) {
            $templatePath = $result->getRelativePath();
            $providedBlocks = $result->getMetric('provided_blocks') ?? [];
            $usedBlocks = $result->getMetric('used_blocks') ?? [];

            foreach ($providedBlocks as $block) {
                $blockDefinitions[$block][] = $templatePath;
            }

            foreach ($usedBlocks as $block) {
                $blockUsage[$block][] = $templatePath;
            }
        }

        return [
            'definitions' => $blockDefinitions,
            'usage' => $blockUsage,
        ];
    }

    /**
     * @param AnalysisResult[]   $results
     * @param array<string, int> $referenceCounter
     *
     * @return array<string, string>
     */
    private function determineArchitecturalRoles(array $results, array $referenceCounter): array
    {
        $roles = [];

        foreach ($results as $result) {
            $templatePath = $result->getRelativePath();
            $metrics = $result->getData();

            $timesReferenced = $referenceCounter[$templatePath] ?? 0;
            $category = $metrics['file_category'] ?? 'other';
            $providedBlocks = count($metrics['provided_blocks'] ?? []);
            $dependencies = count($metrics['dependencies'] ?? []);
            $complexity = $metrics['complexity_score'] ?? 0;

            $role = match (true) {
                $timesReferenced >= 10 => 'architectural_hub',
                $timesReferenced >= 5 && 'component' === $category => 'shared_component',
                $providedBlocks >= 3 && 'layout' === $category => 'base_template',
                0 === $dependencies && 0 === $timesReferenced => 'orphaned',
                $dependencies >= 5 => 'highly_coupled',
                $complexity > 25 => 'complexity_hotspot',
                'page' === $category && $dependencies <= 2 => 'standalone_page',
                default => 'standard_template',
            };

            $roles[$templatePath] = $role;
        }

        return $roles;
    }

    /**
     * @param array<string, int> $referenceCounter
     */
    private function calculatePopularityRank(string $templatePath, array $referenceCounter): int
    {
        $currentReferences = $referenceCounter[$templatePath] ?? 0;
        $higherCount = 0;

        foreach ($referenceCounter as $count) {
            if ($count > $currentReferences) {
                ++$higherCount;
            }
        }

        return $higherCount + 1;
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function calculateCouplingRisk(array $metrics): string
    {
        $dependencies = count($metrics['dependencies'] ?? []);
        $timesReferenced = $metrics['times_referenced'] ?? 0;
        $complexity = $metrics['complexity_score'] ?? 0;

        $risk = 0;
        $risk += $dependencies * 2;
        $risk += $timesReferenced > 10 ? 15 : ($timesReferenced > 5 ? 10 : 0);
        $risk += $complexity > 20 ? 10 : ($complexity > 15 ? 5 : 0);

        return match (true) {
            $risk >= 30 => 'high',
            $risk >= 15 => 'medium',
            default => 'low',
        };
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function calculateReusabilityScore(array $metrics): int
    {
        $score = 100;

        $dependencies = count($metrics['dependencies'] ?? []);
        $complexity = $metrics['complexity_score'] ?? 0;
        $lines = $metrics['lines'] ?? 0;

        $score -= $dependencies * 5;
        $score -= $complexity > 15 ? 20 : ($complexity > 10 ? 10 : 0);
        $score -= $lines > 100 ? 15 : ($lines > 50 ? 5 : 0);

        $providedBlocks = count($metrics['provided_blocks'] ?? []);
        $score += $providedBlocks * 5;

        $category = $metrics['file_category'] ?? 'other';
        if ('component' === $category) {
            $score += 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * @param array<string, mixed> $metrics
     *
     * @return array<int, array<string, string>>
     */
    private function identifyPotentialIssues(array $metrics): array
    {
        $issues = [];

        $complexity = $metrics['complexity_score'] ?? 0;
        if ($complexity > 25) {
            $issues[] = [
                'type' => 'complexity',
                'severity' => 'high',
                'message' => 'Very high complexity detected',
                'suggestion' => 'Break down into smaller templates or components',
            ];
        } elseif ($complexity > 15) {
            $issues[] = [
                'type' => 'complexity',
                'severity' => 'medium',
                'message' => 'High complexity detected',
                'suggestion' => 'Consider refactoring complex logic',
            ];
        }

        $lines = $metrics['lines'] ?? 0;
        if ($lines > 150) {
            $issues[] = [
                'type' => 'size',
                'severity' => 'high',
                'message' => 'Very large template',
                'suggestion' => 'Extract components or break into smaller templates',
            ];
        } elseif ($lines > 75) {
            $issues[] = [
                'type' => 'size',
                'severity' => 'medium',
                'message' => 'Large template',
                'suggestion' => 'Consider component extraction',
            ];
        }

        $couplingRisk = $metrics['coupling_risk'] ?? 'low';
        if ('high' === $couplingRisk) {
            $issues[] = [
                'type' => 'coupling',
                'severity' => 'high',
                'message' => 'High coupling detected',
                'suggestion' => 'Reduce dependencies or extract shared functionality',
            ];
        }

        $role = $metrics['architectural_role'] ?? 'standard';
        if ('orphaned' === $role) {
            $issues[] = [
                'type' => 'maintenance',
                'severity' => 'low',
                'message' => 'Orphaned template (not referenced)',
                'suggestion' => 'Consider removing if truly unused',
            ];
        }

        return $issues;
    }
}
