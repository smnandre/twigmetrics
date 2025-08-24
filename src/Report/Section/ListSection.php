<?php

declare(strict_types=1);

namespace TwigMetrics\Report\Section;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
class ListSection extends ReportSection
{
    /**
     * @param array<string> $items
     */
    public function __construct(
        string $title,
        array $items,
        string $listType = 'bullet',
    ) {
        parent::__construct($title, 'list', [
            'items' => $items,
            'list_type' => $listType,
        ]);
    }

    /**
     * @return string[]
     */
    public function getItems(): array
    {
        return $this->getData()['items'];
    }

    public function getListType(): string
    {
        return $this->getData()['list_type'];
    }
}
