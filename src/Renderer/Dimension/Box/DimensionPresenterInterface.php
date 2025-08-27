<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use TwigMetrics\Analyzer\AnalysisResult;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
interface DimensionPresenterInterface
{
    /**
     * @param array<AnalysisResult> $results
     *
     * @return array<string, mixed>
     */
    public function present(array $results, int $maxDepth, bool $groupByDirectory): array;
}
