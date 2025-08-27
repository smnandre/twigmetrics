<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Helper;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Helper for consistent console output formatting.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class ConsoleOutputHelper
{
    public function __construct(
        private OutputInterface $output,
    ) {
    }

    public function writeHeader(): void
    {
        $this->writeMainHeader();
        $this->writeBetaWarning();
    }

    public function writeMainHeader(): void
    {
        $borderInside = 74;
        $text = 'T W I G   🌿   M E T R I C S';
        $reserved = 3;
        $contentWidth = max(0, $borderInside - 2 * $reserved);
        $pad = max(0, $contentWidth - mb_strlen($text));
        $left = (int) floor($pad / 2) + $reserved;
        $right = ($pad - (int) floor($pad / 2)) + $reserved;
        $centered = str_repeat(' ', $left).$text.str_repeat(' ', $right);

        $top = '  '.sprintf('<fg=#ddd>╭%s╮</>', str_repeat('─', $borderInside)).'  ';
        $middle = '  '.sprintf('<fg=#ddd>│</>%s<fg=#ddd>│</>', $centered).'  ';
        $bottom = '  '.sprintf('<fg=#ddd>╰%s╯</>', str_repeat('─', $borderInside)).'  ';

        $this->output->writeln($top);
        $this->output->writeln($middle);
        $this->output->writeln($bottom);
    }

    public function writeEmptyLine(): void
    {
        $this->output->writeln('');
    }

    public function writeBetaWarning(): void
    {
        $this->output->writeln('  <fg=yellow>⚠</fg=yellow> <fg=gray>- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</fg=gray> <fg=yellow>⚠</fg=yellow>');
        $this->output->writeln('  <fg=gray>|</fg=gray>                                                                         <fg=gray>|</fg=gray>');
        $this->output->writeln('  <fg=gray>|</fg=gray>              <fg=#999>TwigMetrics is </fg=#999><fg=#f1c40f>in development</fg=#f1c40f><fg=#999>. Use with caution.</fg=#999>           <fg=gray>|</fg=gray>');
        $this->output->writeln('  <fg=gray>|</fg=gray>                                                                         <fg=gray>|</fg=gray>');
        $this->output->writeln('  <fg=gray>|</fg=gray>   <fg=yellow>⭑</fg=yellow> <fg=#999>Report issue / feedback</fg=#999>               <fg=#999>Support the development</fg=#999>  <fg=green>♥</fg=green>    <fg=gray>|</fg=gray>');
        $this->output->writeln('  <fg=gray>|</fg=gray>   <fg=#999>github.com/smnandre/twigmetrics</fg=#999>        <fg=#999>github.com/sponsor/smnandre</fg=#999>   <fg=gray>|</fg=gray>');
        $this->output->writeln('  <fg=gray>|</fg=gray>                                                                         <fg=gray>|</fg=gray>');
        $this->output->writeln('  <fg=yellow>⚠</fg=yellow> <fg=gray>- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</fg=gray> <fg=yellow>⚠</fg=yellow>');
    }

    public function writeSeparatorLine(): void
    {
        $this->output->writeln('  <fg=#999>───────────────────────────────────────────────────────────────────────────</>  ');
        $this->output->writeln('');
    }

    public function writeSection(string $title): void
    {
        $this->output->writeln('');
        $this->output->writeln("  <fg=yellow;options=bold>{$title}</fg=yellow;options=bold>  ");
        $this->output->writeln('');
    }

    public function writeSectionTitle(string $title, ?int $number = null): void
    {
        $this->output->writeln('');
        $this->output->writeln('');

        $title = '<fg=bright-white>'.strtoupper($title).'</>';

        if ($number) {
            $title = sprintf('<fg=yellow>%s.</> %s', $number, $title);
        }

        $prefix = \sprintf('  <fg=#90EE90>━━━</> %s  ', $title);
        $totalWidth = 78;
        $remainingWidth = $totalWidth - mb_strlen(strip_tags($prefix));
        $suffix = str_repeat('━', max(0, $remainingWidth));

        $this->output->writeln($prefix.'<fg=#90EE90>'.$suffix.'</> ');
        $this->output->writeln('');
        $this->output->writeln('');
    }

    /**
     * @param array<string|int, string> $leftPairs
     * @param array<string|int, string> $rightPairs
     */
    public function writeTwoColumnKeyValues(array $leftPairs, array $rightPairs, string $leftTitle = '', string $rightTitle = ''): void
    {
        if ($leftTitle) {
            $this->output->writeln(" <fg=yellow;options=bold>{$leftTitle}</fg=yellow;options=bold> ");
            $this->output->writeln('');
        }

        $maxLeftKey = !empty($leftPairs) ? max(array_map('strlen', array_keys($leftPairs))) : 0;
        $maxRightKey = !empty($rightPairs) ? max(array_map('strlen', array_keys($rightPairs))) : 0;

        $maxLines = max(count($leftPairs), count($rightPairs));
        $leftKeys = array_keys($leftPairs);
        $rightKeys = array_keys($rightPairs);

        for ($i = 0; $i < $maxLines; ++$i) {
            $leftKey = $leftKeys[$i] ?? '';
            $leftValue = $leftPairs[$leftKey] ?? '';
            $rightKey = $rightKeys[$i] ?? '';
            $rightValue = $rightPairs[$rightKey] ?? '';

            if ($leftKey || $rightKey) {
                $leftPart = $leftKey ? sprintf('  %-s %s %-8s', $leftKey, str_repeat('.', max(0, 22 - strlen($leftKey))), $leftValue) : str_repeat(' ', 38);
                $rightPart = $rightKey ? sprintf('%-s %s %-8s', $rightKey, str_repeat('.', max(0, 22 - strlen($rightKey))), $rightValue) : '';

                $this->output->writeln(' '.rtrim($leftPart.$rightPart).' ');
            }
        }

        $this->output->writeln('');
    }

    public function writeSectionWithDivider(string $title, string $number = ''): void
    {
        $divider = '── '.($number ? $number.'. ' : '').strtoupper($title).' '.str_repeat('─', max(0, 75 - strlen($title) - strlen($number) - 4));
        $this->output->writeln('');
        $this->output->writeln($divider);
        $this->output->writeln('');
    }

    /**
     * @param array<string, int> $templates
     */
    public function writeTemplateList(array $templates, string $title, int $maxLines = 5): void
    {
        $this->output->writeln(" <fg=yellow;options=bold>{$title}</fg=yellow;options=bold>");

        if (empty($templates)) {
            $this->output->writeln('  No data available');
            $this->output->writeln('');

            return;
        }

        $maxValue = max(array_values($templates)) ?: 1;
        $count = 0;

        foreach ($templates as $name => $value) {
            if ($count >= $maxLines) {
                break;
            }
            ++$count;

            $normalizedValue = $maxValue > 0 ? ($value / $maxValue) : 0;
            $barLength = (int) ($normalizedValue * 20);
            $bar = str_repeat('█', $barLength).str_repeat('░', 20 - $barLength);

            $this->output->writeln(sprintf('  %d. %-30s │ %s %d lines',
                $count,
                $name,
                $bar,
                $value
            ));
        }

        $this->output->writeln('');
    }

    /**
     * @param array<int, array<string, mixed>> $distribution
     */
    public function writeDistribution(array $distribution, string $title): void
    {
        $this->output->writeln(" <fg=yellow;options=bold>{$title}</fg=yellow;options=bold>");

        if (empty($distribution)) {
            $this->output->writeln('  No data available');
            $this->output->writeln('');

            return;
        }

        $total = array_sum(array_column($distribution, 'count'));

        foreach ($distribution as $item) {
            $name = $item['name'];
            $count = $item['count'];
            $percentage = $total > 0 ? ($count / $total) * 100 : 0;

            $barLength = (int) ($percentage / 100 * 22);
            $bar = str_repeat('█', $barLength).str_repeat('─', 22 - $barLength);

            $this->output->writeln(sprintf('  %-12s %2d (%6.2f%%) %s',
                $name,
                $count,
                $percentage,
                $bar
            ));
        }

        $this->output->writeln('');
    }

    public function color(string $text, string $fg, ?string $options = null): string
    {
        $opt = $options ? ";options={$options}" : '';

        return sprintf('<fg=%s%s>%s</>', $fg, $opt, $text);
    }

    public function gray(string $text): string
    {
        return $this->color($text, '#cccccc');
    }

    public function white(string $text, bool $bold = false): string
    {
        return $this->color($text, '#ffffff', $bold ? 'bold' : null);
    }

    public function gradeColor(string $grade): string
    {
        $map = [
            'A+' => 'green', 'A' => 'green',
            'B' => 'cyan',
            'C+' => 'yellow', 'C' => 'yellow',
            'D' => 'red', 'E' => 'red',
        ];
        $fg = $map[$grade] ?? 'red';
        $opts = in_array($grade, ['A+', 'A'], true) ? 'bold' : null;

        return $this->color($grade, $fg, $opts);
    }

    public function riskLabel(string $risk): string
    {
        $label = strtoupper($risk);

        return match ($risk) {
            'low' => $this->color($label, 'green'),
            'moderate', 'medium' => $this->color($label, 'yellow'),
            'high' => $this->color($label, 'red'),
            'critical' => $this->color($label, 'red', 'bold'),
            default => $this->gray($label),
        };
    }
}
