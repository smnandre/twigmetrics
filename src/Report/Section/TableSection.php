<?php

declare(strict_types=1);

namespace TwigMetrics\Report\Section;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
class TableSection extends ReportSection
{
    /**
     * @param array<string>                      $headers
     * @param list<array<int, int|float|string>> $rows
     * @param array<string, mixed>               $options
     */
    public function __construct(
        string $title,
        array $headers,
        array $rows,
        array $options = [],
    ) {
        parent::__construct($title, 'table', [
            'headers' => $headers,
            'rows' => $rows,
        ], $options);
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->getData()['headers'];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function getRows(): array
    {
        return $this->getData()['rows'];
    }
}
