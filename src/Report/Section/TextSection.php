<?php

declare(strict_types=1);

namespace TwigMetrics\Report\Section;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
class TextSection extends ReportSection
{
    public function __construct(
        string $title,
        private string $content,
    ) {
        parent::__construct($title, 'text');
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
