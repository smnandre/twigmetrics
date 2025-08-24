<?php

declare(strict_types=1);

namespace TwigMetrics\Template;

use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TwigFilter;
use Twig\TwigFunction;
use TwigMetrics\Template\Parser\TwigParser;

/**
 * @internal
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class TwigEnvironmentFactory
{
    public static function createForAnalysis(): Environment
    {
        $twig = new Environment(new ArrayLoader());

        $parser = new TwigParser($twig);
        $twig->setParser($parser);

        self::registerFallbackHandlers($twig);

        return $twig;
    }

    private static function registerFallbackHandlers(Environment $twig): void
    {
        $twig->registerUndefinedFunctionCallback(
            static fn (string $name): TwigFunction => new TwigFunction($name, fn (...$args) => null)
        );

        $twig->registerUndefinedFilterCallback(
            static fn (string $name): TwigFilter => new TwigFilter($name, fn (...$args) => null)
        );

        $twig->registerUndefinedTokenParserCallback(
            static fn (string $name): AbstractTokenParser => new class($name) extends AbstractTokenParser {
                public function __construct(private readonly string $tagName)
                {
                }

                public function parse(Token $token): Node
                {
                    $stream = $this->parser->getStream();

                    while (!$stream->test(Token::BLOCK_END_TYPE)) {
                        $stream->next();
                    }
                    $stream->expect(Token::BLOCK_END_TYPE);

                    return new Node();
                }

                public function getTag(): string
                {
                    return $this->tagName;
                }
            }
        );
    }
}
