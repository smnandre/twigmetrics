<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Calculator\StatisticalCalculator;
use TwigMetrics\Metric\Aggregator\DirectoryMetricsAggregator;
use TwigMetrics\Reporter\Dimension\ArchitectureMetricsReporter;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ArchitectureDimensionPresenter implements DimensionPresenterInterface
{
    public function __construct(
        private readonly ArchitectureMetricsReporter $reporter,
        private readonly DirectoryMetricsAggregator $dirAgg,
        private readonly StatisticalCalculator $stats,
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    public function present(array $results, int $maxDepth = 4, bool $includeDirs = true): array
    {
        $card = $this->reporter->generateMetrics($results);

        $core = $card->coreMetrics;
        $detail = $card->detailMetrics;

        $totalTemplates = count($results);
        $relationshipCounts = $this->calculateRelationships($results);

        $blockUsage = $this->calculateBlockUsage($results);
        $macroUsage = $this->calculateMacroUsage($results);

        $inheritance = $this->calculateInheritanceMetrics($results);

        $dirs = [];
        if ($includeDirs && $maxDepth > 0) {
            $dirs = $this->calculateDirectoryHeatmap($results, $maxDepth);
        }

        $topReferenced = $this->findMostReferencedTemplates($results);

        $topBlockNames = $this->findMostUsedBlockNames($results);

        return [
            'summary' => [
                'total_templates' => $totalTemplates,
                'extends_total' => $relationshipCounts['extends_total'],
                'includes_total' => $relationshipCounts['includes_total'],
                'embeds_total' => $relationshipCounts['embeds_total'],
                'blocks_total' => $blockUsage['definitions'],
                'extends_per_template' => $relationshipCounts['extends_per_template'],
                'includes_per_template' => $relationshipCounts['includes_per_template'],
                'embeds_per_template' => $relationshipCounts['embeds_per_template'],

                'imports_per_template' => $relationshipCounts['imports_per_template'] ?? 0.0,
                'avg_inheritance_depth' => $inheritance['avg_depth'] ?? 0.0,
                'blocks_per_template' => $blockUsage['definitions_per_template'],
            ],
            'inheritance' => [
                'max_depth' => $inheritance['max_depth'],
                'avg_depth' => $inheritance['avg_depth'],
                'root_templates' => $inheritance['root_templates'],
                'orphan_files' => $inheritance['orphan_files'],
            ],
            'blocks' => [
                'definitions' => $blockUsage['definitions'],
                'calls' => $blockUsage['calls'],
                'overrides' => $blockUsage['overrides'],
                'unused' => $blockUsage['unused'],
            ],
            'macros' => [
                'definitions' => $macroUsage['definitions'],
                'calls' => $macroUsage['calls'],
                'external_calls' => $macroUsage['external_calls'],
                'unused' => $macroUsage['unused'],
            ],
            'directories' => $dirs,
            'top_referenced' => $topReferenced,
            'top_blocks' => $topBlockNames,
            'inheritance_patterns' => $this->buildInheritanceTree($results),
            'final' => [
                'score' => (float) $card->score,
                'grade' => (string) $card->grade,
            ],
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function calculateRelationships(array $results): array
    {
        $extends = 0;
        $includes = 0;
        $embeds = 0;
        $uses = 0;
        $imports = 0;

        foreach ($results as $result) {
            $deps = $result->getMetric('dependency_types') ?? [];
            if (is_array($deps)) {
                $extends += (int) ($deps['extends'] ?? 0);
                $includes += (int) ($deps['includes'] ?? 0) + (int) ($deps['includes_function'] ?? 0);
                $embeds += (int) ($deps['embeds'] ?? 0);
                $uses += (int) ($deps['uses'] ?? 0);
                $imports += (int) ($deps['imports'] ?? 0);
            }
        }

        $total = count($results);

        return [
            'extends_total' => $extends,
            'includes_total' => $includes,
            'embeds_total' => $embeds,
            'uses_total' => $uses,
            'imports_total' => $imports,
            'extends_per_template' => $total > 0 ? round($extends / $total, 2) : 0.0,
            'includes_per_template' => $total > 0 ? round($includes / $total, 2) : 0.0,
            'embeds_per_template' => $total > 0 ? round($embeds / $total, 2) : 0.0,
            'imports_per_template' => $total > 0 ? round($imports / $total, 2) : 0.0,
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function calculateBlockUsage(array $results): array
    {
        $definitions = 0;
        $calls = 0;
        $overrides = 0;

        foreach ($results as $result) {
            $blocks = $result->getMetric('blocks_detail') ?? [];
            if (is_array($blocks)) {
                $definitions += count($blocks);
            }

            $blockCalls = $result->getMetric('block_calls') ?? 0;
            $blockOverrides = $result->getMetric('block_overrides') ?? 0;
            $calls += (int) $blockCalls;
            $overrides += (int) $blockOverrides;
        }

        $unused = max(0, $definitions - $calls);

        return [
            'definitions' => $definitions,
            'calls' => $calls,
            'overrides' => $overrides,
            'unused' => $unused,
            'definitions_per_template' => count($results) > 0 ? round($definitions / count($results), 2) : 0.0,
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function calculateMacroUsage(array $results): array
    {
        $definitions = 0;
        $calls = 0;

        foreach ($results as $result) {
            $macros = $result->getMetric('macro_definitions_detail') ?? [];
            if (is_array($macros)) {
                $definitions += count($macros);
            }

            $macroCalls = $result->getMetric('macro_calls') ?? 0;
            $calls += (int) $macroCalls;
        }

        return [
            'definitions' => $definitions,
            'calls' => $calls,
            'external_calls' => max(0, $calls - $definitions),
            'unused' => max(0, $definitions - $calls),
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function calculateInheritanceMetrics(array $results): array
    {
        $maxDepth = 0;
        $depths = [];
        $orphans = 0;

        foreach ($results as $result) {
            $depth = (int) ($result->getMetric('inheritance_depth') ?? 0);
            $maxDepth = max($maxDepth, $depth);
            $depths[] = $depth;

            $deps = $result->getMetric('dependencies') ?? [];
            if (empty($deps)) {
                ++$orphans;
            }
        }

        $avgDepth = count($depths) > 0 ? $this->stats->calculate($depths)->mean : 0.0;

        $roots = 0;
        foreach ($results as $result) {
            $deps = $result->getMetric('dependency_types') ?? [];
            $extends = (int) ($deps['extends'] ?? 0);
            if (0 === $extends) {
                ++$roots;
            }
        }

        return [
            'max_depth' => $maxDepth,
            'avg_depth' => round($avgDepth, 1),
            'root_templates' => $roots,
            'orphan_files' => $orphans,
        ];
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return list<array<string, float|int|string>>
     */
    private function calculateDirectoryHeatmap(array $results, int $maxDepth): array
    {
        $aggr = $this->dirAgg->aggregateByDirectory($results, $maxDepth);
        $dirs = [];

        $maxExtends = 1;
        $maxIncludes = 1;
        $maxEmbeds = 1;
        $maxBlocks = 1;

        foreach ($aggr as $metrics) {
            $extends = $this->countDependencyTypeInDirectory($results, $metrics->path, 'extends');
            $includes = $this->countDependencyTypeInDirectory($results, $metrics->path, 'includes');
            $embeds = $this->countDependencyTypeInDirectory($results, $metrics->path, 'embeds');
            $blocks = $this->countBlocksInDirectory($results, $metrics->path);

            $maxExtends = max($maxExtends, $extends);
            $maxIncludes = max($maxIncludes, $includes);
            $maxEmbeds = max($maxEmbeds, $embeds);
            $maxBlocks = max($maxBlocks, $blocks);
        }

        foreach ($aggr as $path => $metrics) {
            $extends = $this->countDependencyTypeInDirectory($results, $path, 'extends');
            $includes = $this->countDependencyTypeInDirectory($results, $path, 'includes');
            $embeds = $this->countDependencyTypeInDirectory($results, $path, 'embeds');
            $blocks = $this->countBlocksInDirectory($results, $path);

            $dirs[] = [
                'path' => $path,
                'extends_ratio' => $extends / $maxExtends,
                'includes_ratio' => $includes / $maxIncludes,
                'embeds_ratio' => $embeds / $maxEmbeds,
                'blocks_ratio' => $blocks / $maxBlocks,
            ];
        }

        return $dirs;
    }

    /**
     * Count occurrences of a specific dependency type in files under a directory.
     *
     * @param AnalysisResult[] $results
     */
    private function countDependencyTypeInDirectory(array $results, string $dirPath, string $type): int
    {
        $count = 0;
        foreach ($results as $result) {
            if (str_starts_with($result->getRelativePath(), $dirPath)) {
                $deps = $result->getMetric('dependency_types') ?? [];
                if (is_array($deps)) {
                    $count += (int) ($deps[$type] ?? 0);
                    if ('includes' === $type) {
                        $count += (int) ($deps['includes_function'] ?? 0);
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Count total block definitions in files under a directory.
     *
     * @param AnalysisResult[] $results
     */
    private function countBlocksInDirectory(array $results, string $dirPath): int
    {
        $count = 0;
        foreach ($results as $result) {
            if (str_starts_with($result->getRelativePath(), $dirPath)) {
                $blocks = $result->getMetric('blocks_detail') ?? [];
                if (is_array($blocks)) {
                    $count += count($blocks);
                }
            }
        }

        return $count;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<int, array<string, mixed>>
     */
    private function findMostReferencedTemplates(array $results): array
    {
        $references = [];

        foreach ($results as $result) {
            $deps = $result->getMetric('dependencies') ?? [];
            if (is_array($deps)) {
                foreach ($deps as $dep) {
                    $template = is_array($dep) && isset($dep['template']) ? $dep['template'] : (string) $dep;
                    if (!empty($template)) {
                        $references[$template] = ($references[$template] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($references);
        $top = [];
        $count = 0;
        foreach ($references as $template => $refCount) {
            $top[] = [
                'template' => $template,
                'count' => $refCount,
            ];
            if (++$count >= 5) {
                break;
            }
        }

        return $top;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<int, array<string, mixed>>
     */
    private function findMostUsedBlockNames(array $results): array
    {
        $blockNames = [];

        foreach ($results as $result) {
            $blocks = $result->getMetric('blocks_detail') ?? [];
            foreach ($blocks as $blockName) {
                $blockNames[$blockName] ??= 0;
                ++$blockNames[$blockName];
            }
        }

        arsort($blockNames);
        $top = [];
        $count = 0;
        foreach ($blockNames as $name => $usage) {
            $top[] = [
                'name' => $name,
                'count' => $usage,
            ];
            if (++$count >= 10) {
                break;
            }
        }

        return $top;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, mixed>
     */
    private function buildInheritanceTree(array $results): array
    {
        $roots = [];
        $children = [];

        foreach ($results as $result) {
            $deps = $result->getMetric('dependency_types') ?? [];
            $extends = (int) ($deps['extends'] ?? 0);
            $path = $result->getRelativePath();

            if (0 === $extends && str_contains($path, 'base')) {
                $roots[] = $path;
            }

            $dependencies = $result->getMetric('dependencies') ?? [];
            if (is_array($dependencies)) {
                foreach ($dependencies as $dep) {
                    $template = is_array($dep) && isset($dep['template']) ? $dep['template'] : (string) $dep;
                    $type = is_array($dep) && isset($dep['type']) ? $dep['type'] : 'unknown';

                    if ('extends' === $type && !empty($template)) {
                        $children[$template] = ($children[$template] ?? 0) + 1;
                    }
                }
            }
        }

        return [
            'roots' => $roots,
            'children' => $children,
        ];
    }
}
