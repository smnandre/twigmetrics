<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Component;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class TopUsageRenderer
{
    private const int TOTAL_WIDTH = 80;
    private const int GAP = 2;
    private const int COLUMN_WIDTH = (self::TOTAL_WIDTH - self::GAP) / 2;
    private const int PADDING_LEFT = 1;
    private const int BAR_WIDTH = 10;

    public function __construct(
        private OutputInterface $output,
    ) {
    }

    /**
     * @param array<string, mixed> $functions
     * @param array<string, mixed> $variables
     */
    public function render(array $functions, array $variables, int $limit = 10): void
    {
        $topFunctions = $this->getTopItems($functions, $limit);
        $topVariables = $this->getTopItems($variables, $limit);

        $this->renderHeaders();
        $this->renderRows($topFunctions, $topVariables);
    }

    private function renderHeaders(): void
    {
        $functionsHeader = $this->formatColumn('Most used Functions (top 10)');
        $variablesHeader = $this->formatColumn('Most used Variables (top 10)');

        $this->output->writeln(
            sprintf('<fg=cyan>%s</>%s<fg=cyan>%s</>',
                $functionsHeader,
                str_repeat(' ', self::GAP),
                $variablesHeader
            )
        );
    }

    /**
     * @param array<int, array<string, int|string>> $functions
     * @param array<int, array<string, int|string>> $variables
     */
    private function renderRows(array $functions, array $variables): void
    {
        $maxCount = max(count($functions), count($variables));
        $maxFunctionUsage = !empty($functions) ? max(array_column($functions, 'usage')) : 0;
        $maxVariableUsage = !empty($variables) ? max(array_column($variables, 'usage')) : 0;

        for ($i = 0; $i < $maxCount; ++$i) {
            $functionLine = $this->renderItem(
                $functions[$i] ?? null,
                $maxFunctionUsage
            );

            $variableLine = $this->renderItem(
                $variables[$i] ?? null,
                $maxVariableUsage
            );

            $this->output->writeln(
                sprintf('%s%s%s',
                    $functionLine,
                    str_repeat(' ', self::GAP),
                    $variableLine
                )
            );
        }
    }

    /**
     * @param array<string, int|string>|null $item
     */
    private function renderItem(?array $item, int $maxUsage): string
    {
        if (null === $item) {
            return str_repeat(' ', (int) self::COLUMN_WIDTH);
        }

        $name = $item['name'];
        $usage = $item['usage'];

        $barLength = $maxUsage > 0 ? ($usage / $maxUsage) * self::BAR_WIDTH : 0;
        $bar = $this->createProgressBar($barLength);

        $nameAndUsage = sprintf('%s %d', $name, $usage);

        $availableNameSpace = (int) self::COLUMN_WIDTH - self::PADDING_LEFT - self::BAR_WIDTH - 2;

        if (strlen($nameAndUsage) > $availableNameSpace) {
            $nameAndUsage = substr($nameAndUsage, 0, $availableNameSpace - 1).'…';
        }

        $spacesBeforeBar = $availableNameSpace - strlen($nameAndUsage);

        $content = sprintf('%s%s %s',
            $nameAndUsage,
            str_repeat(' ', max(1, $spacesBeforeBar)),
            $bar
        );

        return $this->formatColumn($content);
    }

    private function createProgressBar(float $length): string
    {
        $fullBlocks = (int) $length;
        $remainder = $length - $fullBlocks;

        $bar = str_repeat('█', $fullBlocks);

        if ($remainder > 0 && $fullBlocks < self::BAR_WIDTH) {
            $bar .= $this->getPartialBlock($remainder);
        }

        $bar = str_pad($bar, self::BAR_WIDTH, ' ', STR_PAD_RIGHT);

        return sprintf('<fg=green>%s</>', $bar);
    }

    private function getPartialBlock(float $fraction): string
    {
        return match (true) {
            $fraction >= 0.875 => '█',
            $fraction >= 0.75 => '▉',
            $fraction >= 0.625 => '▊',
            $fraction >= 0.5 => '▋',
            $fraction >= 0.375 => '▌',
            $fraction >= 0.25 => '▍',
            $fraction >= 0.125 => '▎',
            default => '▏',
        };
    }

    private function formatColumn(string $content): string
    {
        $paddedContent = str_repeat(' ', self::PADDING_LEFT).$content;

        return str_pad($paddedContent, (int) self::COLUMN_WIDTH, ' ', STR_PAD_RIGHT);
    }

    /**
     * @param array<string, int> $usageData
     *
     * @return array<int, array<string, string|int>>
     */
    private function getTopItems(array $usageData, int $limit): array
    {
        arsort($usageData);

        $topItems = array_slice($usageData, 0, $limit, true);

        $result = [];
        foreach ($topItems as $name => $usage) {
            $result[] = [
                'name' => $name,
                'usage' => $usage,
            ];
        }

        return $result;
    }
}
