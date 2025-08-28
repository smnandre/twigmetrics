<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

use TwigMetrics\Metric\BlockMetrics;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class BlockUsageAnalyzer
{
    /**
     * @param AnalysisResult[] $results
     */
    public function analyzeBlockUsage(array $results): BlockMetrics
    {
        $defined = [];
        $used = [];
        $orphaned = [];

        foreach ($results as $result) {
            $blocks = $result->getMetric('provided_blocks') ?? $result->getMetric('blocksDefinitions') ?? [];
            if (is_array($blocks)) {
                foreach ($blocks as $blockName) {
                    $defined[$blockName] = ($defined[$blockName] ?? 0) + 1;
                }
            }
        }

        foreach ($results as $result) {
            $usages = $result->getMetric('used_blocks') ?? $result->getMetric('blocksUsage') ?? [];
            if (is_array($usages)) {
                foreach ($usages as $blockName) {
                    $used[$blockName] = ($used[$blockName] ?? 0) + 1;
                }
            }
        }

        foreach ($defined as $blockName => $count) {
            if (!isset($used[$blockName])) {
                $orphaned[] = (string) $blockName;
            }
        }

        $usageCount = count($used);
        $definedCount = count($defined);

        return new BlockMetrics(
            totalDefined: array_sum($defined),
            totalUsed: array_sum($used),
            orphanedBlocks: $orphaned,
            usageRatio: $definedCount > 0 ? $usageCount / $definedCount : 0.0,
            averageReuse: $usageCount > 0 ? array_sum($used) / $usageCount : 0.0,
        );
    }
}
