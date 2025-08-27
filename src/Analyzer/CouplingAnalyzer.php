<?php

declare(strict_types=1);

namespace TwigMetrics\Analyzer;

use TwigMetrics\Metric\CouplingMetrics;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class CouplingAnalyzer
{
    /**
     * @param AnalysisResult[] $results
     */
    public function analyzeCoupling(array $results): CouplingMetrics
    {
        $graph = $this->buildDependencyGraph($results);

        $fanIn = array_fill_keys(array_keys($graph), 0);
        $fanOut = [];

        foreach ($graph as $src => $deps) {
            $uniqueDeps = array_values(array_unique($deps));
            $fanOut[$src] = count($uniqueDeps);
            foreach ($uniqueDeps as $dst) {
                if (!array_key_exists($dst, $fanIn)) {
                    $fanIn[$dst] = 0;
                }
                ++$fanIn[$dst];
            }
        }

        $avgFanIn = $this->avg($fanIn);
        $avgFanOut = $this->avg($fanOut);

        $maxCoupling = 0;
        foreach (array_keys($graph) as $node) {
            $coupling = ($fanIn[$node] ?? 0) + ($fanOut[$node] ?? 0);
            $maxCoupling = max($maxCoupling, $coupling);
        }

        $instability = $this->instabilityIndex($fanIn, $fanOut);
        $circular = $this->detectCircularReferences($graph);

        return new CouplingMetrics(
            avgFanIn: $avgFanIn,
            avgFanOut: $avgFanOut,
            maxCoupling: $maxCoupling,
            instabilityIndex: $instability,
            circularRefs: $circular,
        );
    }

    /**
     * @param AnalysisResult[] $results
     *
     * @return array<string, list<string>>
     */
    private function buildDependencyGraph(array $results): array
    {
        $graph = [];
        foreach ($results as $result) {
            $templatePath = $result->getRelativePath();
            $graph[$templatePath] = [];
            $dependencies = $result->getMetric('dependencies') ?? [];
            if (is_array($dependencies)) {
                foreach ($dependencies as $dep) {
                    if (is_array($dep) && isset($dep['template'])) {
                        $graph[$templatePath][] = (string) $dep['template'];
                    } elseif (is_string($dep)) {
                        $graph[$templatePath][] = $dep;
                    }
                }
            }
        }

        return $graph;
    }

    /**
     * @param array<string,int> $fanIn
     * @param array<string,int> $fanOut
     */
    private function instabilityIndex(array $fanIn, array $fanOut): float
    {
        $nodes = array_unique([...array_keys($fanIn), ...array_keys($fanOut)]);
        $sum = 0.0;
        $n = max(1, count($nodes));
        foreach ($nodes as $node) {
            $in = $fanIn[$node] ?? 0;
            $out = $fanOut[$node] ?? 0;
            $den = $in + $out;
            $sum += $den > 0 ? $out / $den : 0.0;
        }

        return $sum / $n;
    }

    /**
     * @param array<string, list<string>> $graph
     */
    private function detectCircularReferences(array $graph): int
    {
        $visited = [];
        $stack = [];
        $cycles = 0;

        $visit = function (string $node) use (&$visit, &$visited, &$stack, $graph, &$cycles): void {
            if (($visited[$node] ?? false) === true) {
                return;
            }
            $visited[$node] = true;
            $stack[$node] = true;
            foreach ($graph[$node] ?? [] as $nbr) {
                if (!isset($visited[$nbr])) {
                    $visit($nbr);
                } elseif (($stack[$nbr] ?? false) === true) {
                    ++$cycles;
                }
            }
            $stack[$node] = false;
        };

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $visit($node);
            }
        }

        return $cycles;
    }

    /**
     * @param array<string,int> $arr
     */
    private function avg(array $arr): float
    {
        $n = count($arr);

        return $n > 0 ? array_sum($arr) / $n : 0.0;
    }
}
