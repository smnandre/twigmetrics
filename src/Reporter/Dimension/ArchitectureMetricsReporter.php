<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter\Dimension;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Analyzer\BlockUsageAnalyzer;
use TwigMetrics\Analyzer\CouplingAnalyzer;
use TwigMetrics\Metric\DimensionMetrics;
use TwigMetrics\Reporter\Helper\DimensionGrader;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ArchitectureMetricsReporter
{
    public function __construct(
        private readonly CouplingAnalyzer $coupling,
        private readonly BlockUsageAnalyzer $blockUsage,
        private readonly DimensionGrader $grader = new DimensionGrader(),
    ) {
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generateMetrics(array $results): DimensionMetrics
    {
        $coupling = $this->coupling->analyzeCoupling($results);
        $roles = $this->classifyRoles($results);
        [$graph, $in, $out] = $this->buildGraphStats($results);
        $orphans = $this->findOrphans($in, $out);

        $maxDepth = $this->maxInheritanceDepth($results);
        $componentsTotal = $roles['components'] ?? 0;
        $rolesTotal = array_sum($roles);
        $componentsRatio = $rolesTotal > 0 ? $componentsTotal / $rolesTotal : 0.0;
        $orphanRatio = $rolesTotal > 0 ? count($orphans) / $rolesTotal : 0.0;

        [$score, $grade] = $this->grader->gradeArchitecture(
            componentsRatio: $componentsRatio,
            orphanRatio: $orphanRatio,
            circularRefs: $coupling->circularRefs,
            maxDepth: $maxDepth,
        );

        $blockMetrics = $this->blockUsage->analyzeBlockUsage($results);

        return new DimensionMetrics(
            name: 'Architecture',
            score: $score,
            grade: $grade,
            coreMetrics: [
                'avg_fan_in' => round($coupling->avgFanIn, 2),
                'avg_fan_out' => round($coupling->avgFanOut, 2),
                'instability' => round($coupling->instabilityIndex, 2),
                'circular_refs' => $coupling->circularRefs,
                'orphans' => count($orphans),
                'max_inheritance_depth' => $maxDepth,
            ],
            detailMetrics: [
                'components_ratio' => round($componentsRatio, 3),
                'orphan_ratio' => round($orphanRatio, 3),
                'block_usage_ratio' => round($blockMetrics->usageRatio, 3),
                'orphaned_blocks' => count($blockMetrics->orphanedBlocks),
            ],
            distributions: [
                'roles' => $roles,
            ],
            insights: $this->insights($orphans, $coupling->circularRefs, $maxDepth, $roles),
        );
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string,int>
     */
    private function classifyRoles(array $results): array
    {
        $roles = ['components' => 0, 'pages' => 0, 'layouts' => 0, 'other' => 0];
        foreach ($results as $r) {
            $category = (string) ($r->getMetric('file_category') ?? '');
            $path = $r->getRelativePath();
            if ('component' === $category || str_contains($path, 'component')) {
                ++$roles['components'];
            } elseif ('page' === $category || str_contains($path, 'page')) {
                ++$roles['pages'];
            } elseif ('layout' === $category || str_contains($path, 'layout') || str_contains($path, 'base')) {
                ++$roles['layouts'];
            } else {
                ++$roles['other'];
            }
        }

        return $roles;
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array{0: array<string,list<string>>, 1: array<string,int>, 2: array<string,int>}
     */
    private function buildGraphStats(array $results): array
    {
        $graph = [];
        $in = [];
        $out = [];
        foreach ($results as $r) {
            $src = $r->getRelativePath();
            $deps = $r->getMetric('dependencies') ?? [];
            $graph[$src] = [];
            foreach (is_array($deps) ? $deps : [] as $d) {
                $tpl = is_array($d) && isset($d['template']) ? (string) $d['template'] : (string) $d;
                if ('' === $tpl) {
                    continue;
                }
                $graph[$src][] = $tpl;
            }
        }

        foreach ($graph as $src => $deps) {
            $out[$src] = count(array_unique($deps));
            foreach (array_unique($deps) as $dst) {
                $in[$dst] = ($in[$dst] ?? 0) + 1;
            }
            $in[$src] = $in[$src] ?? 0;
        }

        return [$graph, $in, $out];
    }

    /**
     * @param array<string,int> $in
     * @param array<string,int> $out
     *
     * @return list<string>
     */
    private function findOrphans(array $in, array $out): array
    {
        $orphans = [];
        foreach ($out as $node => $o) {
            $i = $in[$node] ?? 0;
            if (0 === $i && 0 === $o) {
                $orphans[] = $node;
            }
        }

        return $orphans;
    }

    /**
     * @param AnalysisResult[] $results
     */
    private function maxInheritanceDepth(array $results): int
    {
        $max = 0;
        foreach ($results as $r) {
            $depth = (int) ($r->getMetric('inheritance_depth') ?? 0);
            if ($depth > $max) {
                $max = $depth;
            }
        }

        return $max;
    }

    /**
     * @param list<string>      $orphans
     * @param array<string,int> $roles
     *
     * @return list<string>
     */
    private function insights(array $orphans, int $circularRefs, int $maxDepth, array $roles): array
    {
        $insights = [];
        if ($circularRefs > 0) {
            $insights[] = sprintf('Circular refs detected: %d', $circularRefs);
        }
        if (!empty($orphans)) {
            $insights[] = sprintf('Orphaned templates: %d', count($orphans));
        }
        if ($maxDepth > 4) {
            $insights[] = sprintf('Deep inheritance chains (max depth: %d)', $maxDepth);
        }
        if (($roles['components'] ?? 0) > ($roles['pages'] ?? 0)) {
            $insights[] = 'Component-oriented architecture';
        }

        return $insights;
    }
}
