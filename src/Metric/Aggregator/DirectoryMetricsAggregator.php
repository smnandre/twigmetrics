<?php

declare(strict_types=1);

namespace TwigMetrics\Metric\Aggregator;

use TwigMetrics\Analyzer\AnalysisResult;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class DirectoryMetricsAggregator
{
    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, DirectoryMetrics>
     */
    public function aggregateByDirectory(array $results, int $maxDepth = 2): array
    {
        $directories = [];

        foreach ($results as $result) {
            $path = $result->getRelativePath();
            $parts = explode('/', $path);
            $levels = min($maxDepth, max(1, count($parts) - 1));
            for ($depth = 1; $depth <= $levels; ++$depth) {
                $dirPath = implode('/', array_slice($parts, 0, $depth));
                if (!isset($directories[$dirPath])) {
                    $directories[$dirPath] = new DirectoryMetrics($dirPath);
                }
                $directories[$dirPath]->addResult($result);
            }
        }

        ksort($directories);

        return $directories;
    }
}
