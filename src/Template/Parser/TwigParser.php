<?php

declare(strict_types=1);

namespace TwigMetrics\Template\Parser;

use Twig\Parser;

/**
 * @internal
 */
/**
 * @author Simon André <smn.andre@gmail.com>
 */
class TwigParser extends Parser
{
    public function shouldIgnoreUnknownTwigCallables(): bool
    {
        return false;
    }
}
