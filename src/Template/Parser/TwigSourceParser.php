<?php

declare(strict_types=1);

namespace TwigMetrics\Template\Parser;

use Twig\Environment;
use Twig\Node\Node;
use Twig\Source;

/**
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class TwigSourceParser
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function parseFile(\SplFileInfo $file): Node
    {
        $content = $this->readFileContent($file);
        $source = new Source($content, $file->getFilename(), $file->getPathname());

        return $this->twig->parse($this->twig->tokenize($source));
    }

    public function parseString(string $content, string $name = 'template'): Node
    {
        $source = new Source($content, $name);

        return $this->twig->parse($this->twig->tokenize($source));
    }

    private function readFileContent(\SplFileInfo $file): string
    {
        $content = @file_get_contents($file->getPathname());

        if (false === $content) {
            throw new \RuntimeException(sprintf('Unable to read file: %s', $file->getPathname()));
        }

        return $content;
    }
}
