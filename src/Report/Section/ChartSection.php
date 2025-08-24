<?php

declare(strict_types=1);

namespace TwigMetrics\Report\Section;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
class ChartSection extends ReportSection
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $title,
        string $chartType,
        array $data,
        array $options = [],
    ) {
        parent::__construct($title, 'chart', [
            'chart_type' => $chartType,
            'chart_data' => $data,
        ], $options);
    }

    public function getChartType(): string
    {
        return $this->getData()['chart_type'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getChartData(): array
    {
        return $this->getData()['chart_data'];
    }
}
