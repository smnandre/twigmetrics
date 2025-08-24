<?php

declare(strict_types=1);

namespace TwigMetrics\Reporter;

use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Report\Report;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class SummaryReporter implements ReporterInterface
{
    private QualityReporter $healthReporter;

    public function __construct()
    {
        $this->healthReporter = new QualityReporter();
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function generate(array $results): Report
    {
        return $this->healthReporter->generate($results);
    }

    public function getWeight(): float
    {
        return 0.0;
    }

    public function getDimensionName(): string
    {
        return 'default-analysis';
    }
}
