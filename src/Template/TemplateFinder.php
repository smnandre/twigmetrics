<?php

declare(strict_types=1);

namespace TwigMetrics\Template;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class TemplateFinder
{
    /**
     * @return iterable<SplFileInfo>
     */
    public function find(string $path): iterable
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Path "%s" is not a valid directory', $path));
        }

        $finder = new Finder();
        $finder->files()
            ->in($path)
            ->name(['*.html.twig', '*.twig'])
            ->sortByName();

        return $finder;
    }
}
