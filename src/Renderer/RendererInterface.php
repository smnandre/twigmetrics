<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer;

use TwigMetrics\Report\Report;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
interface RendererInterface
{
    public function render(Report $report): string;
}
