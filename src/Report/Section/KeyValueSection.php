<?php

declare(strict_types=1);

namespace TwigMetrics\Report\Section;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
class KeyValueSection extends ReportSection
{
    /**
     * @param array<string, string> $pairs
     * @param array<string, mixed>  $options
     */
    public function __construct(
        string $title,
        array $pairs,
        array $options = [],
    ) {
        parent::__construct($title, 'keyvalue', [
            'pairs' => $pairs,
        ], $options);
    }

    /**
     * @return array<string, string>
     */
    public function getPairs(): array
    {
        return $this->getData()['pairs'];
    }
}
