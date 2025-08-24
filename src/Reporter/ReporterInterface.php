<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
interface ReporterInterface
{
    /**
     * @param AnalysisResult[] $results
     */
    public function generate(array $results): Report;

    public function getWeight(): float;

    public function getDimensionName(): string;
}
