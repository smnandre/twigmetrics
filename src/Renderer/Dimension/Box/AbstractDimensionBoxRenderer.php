<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer\Dimension\Box;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
abstract class AbstractDimensionBoxRenderer
{
    protected int $boxWidth = 68;

    public function __construct(protected readonly OutputInterface $output)
    {
    }

    /**
     * Render the dimension box with provided data.
     *
     * @param array<string, mixed> $data
     */
    abstract public function render(array $data): void;

    final protected function header(string $title): void
    {
        $titleLength = mb_strlen($title);
        $borderWidth = $this->boxWidth + 6;
        $availableSpace = $borderWidth - 2;

        if ($titleLength + 2 > $availableSpace) {
            $this->output->writeln('  <fg=#dddddd>╭'.str_repeat('─', $borderWidth).'╮</>  ');
            $this->contentLine(str_pad('<options=bold;fg=cyan>'.$title.'</>', $this->boxWidth, ' ', STR_PAD_BOTH));
        } else {
            $remainingSpace = $availableSpace - $titleLength;
            $leftDashes = (int) floor($remainingSpace / 2);
            $rightDashes = $remainingSpace - $leftDashes;

            $headerLine = '<fg=#dddddd>╭'.str_repeat('─', $leftDashes).'</> '
                .'<options=bold;fg=cyan>'.$title.'</> '
                .'<fg=#dddddd>'.str_repeat('─', $rightDashes).'╮</>';
            $this->output->writeln('  '.$headerLine.'  ');
        }

        $this->blank();
    }

    final protected function footer(): void
    {
        $borderWidth = $this->boxWidth + 6;
        $this->output->writeln('  <fg=#dddddd>╰'.str_repeat('─', $borderWidth).'╯</>  ');
    }

    final protected function divider(string $label): void
    {
        $this->blank();

        $labelLength = mb_strlen($label);
        $availableSpace = $this->boxWidth - 2;

        $remainingSpace = $availableSpace - $labelLength;
        $leftDashes = (int) floor($remainingSpace / 2);
        $rightDashes = $remainingSpace - $leftDashes;

        $line = '<fg=#666666>'.str_repeat('─', $leftDashes + 2).'</>  '
            .'<fg=#aaaaaa>'.$label.'</> '
            .'<fg=#666666> '.str_repeat('─', $rightDashes + 2).'</>';

        $this->output->writeln('  <fg=#999999>│</>'.$line.'<fg=#999999>│</>  ');
    }

    final protected function blank(): void
    {
        $this->contentLine('');
    }

    final protected function twoCols(string $l, string $lv, string $r, string $rv, bool $raw = false): void
    {
        $left = sprintf('%-20s %10s', $this->trim($l, 20), $lv);

        $right = sprintf('%-20s %10s', $this->trim($r, 20), $rv);

        $left = $this->highlightRightAlignedValue($left);
        $right = $this->highlightRightAlignedValue($right);
        $content = $this->padVisual($left, 31).'      '.$this->padVisual($right, 31);
        $this->contentLine($content);
    }

    private function highlightRightAlignedValue(string $col): string
    {
        return preg_replace('/(\s*)(\S+)$/u', '$1<fg=#ffffff;options=bold>$2</>', $col, 1) ?? $col;
    }

    protected function padVisual(string $text, int $width): string
    {
        $visual = $this->getVisualLength($text);
        $pad = max(0, $width - $visual);

        return $text.str_repeat(' ', $pad);
    }

    final protected function gauge(float $score): string
    {
        $width = 20;
        $filled = (int) round(max(0.0, min(100.0, $score)) / 5);
        $empty = $width - $filled;

        $color = $this->getScoreColor((int) round($score));

        $filledBar = str_repeat('█', $filled);
        $emptyBar = str_repeat('█', $empty);

        return "<fg={$color}>{$filledBar}</fg={$color}><fg=black>{$emptyBar}</fg=black>";
    }

    /**
     * Get color based on score thresholds.
     */
    private function getScoreColor(int $score): string
    {
        return match (true) {
            $score >= 90 => 'green',
            $score >= 80 => 'cyan',
            $score >= 70 => 'yellow',
            $score >= 60 => 'magenta',
            default => 'red',
        };
    }

    /**
     * Calculate visual length of string excluding color tags.
     */
    protected function getVisualLength(string $text): int
    {
        $stripped = preg_replace('/\e\[[0-9;]*m/', '', $text) ?? '';
        $stripped = preg_replace('/<[^>]+>/', '', $stripped) ?? $stripped;

        return mb_strlen($stripped);
    }

    /**
     * Trim string to visual width while preserving color tags.
     */
    protected function trimWithColorTags(string $text, int $maxWidth): string
    {
        $visualLength = $this->getVisualLength($text);
        if ($visualLength <= $maxWidth) {
            return $text;
        }
        $tokens = preg_split('/(<[^>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $result = '';
        $current = 0;
        $open = 0;

        foreach ($tokens as $token) {
            if (preg_match('/^<[^>]+>$/', $token)) {
                if ('</>' === $token || str_starts_with($token, '</')) {
                    if ($open > 0) {
                        --$open;
                    }
                    $result .= $token;
                } else {
                    ++$open;
                    $result .= $token;
                }
                continue;
            }

            $len = mb_strlen($token);
            $remain = $maxWidth - $current;
            if ($len <= $remain) {
                $result .= $token;
                $current += $len;
                continue;
            }

            if ($remain > 1) {
                $result .= mb_substr($token, 0, $remain - 1).'…';
            }

            $result .= str_repeat('</>', $open);

            return $result;
        }

        return $result;
    }

    protected function truncateWithColorTags(string $text, int $maxWidth): string
    {
        $visualLength = $this->getVisualLength($text);
        if ($visualLength <= $maxWidth) {
            return $text;
        }

        $tokens = preg_split('/(<[^>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $result = '';
        $current = 0;
        $open = 0;

        foreach ($tokens as $token) {
            if (preg_match('/^<[^>]+>$/', $token)) {
                if ('</>' === $token || str_starts_with($token, '</')) {
                    if ($open > 0) {
                        --$open;
                    }
                    $result .= $token;
                } else {
                    ++$open;
                    $result .= $token;
                }
                continue;
            }

            $len = mb_strlen($token);
            $remain = $maxWidth - $current;
            if ($len <= $remain) {
                $result .= $token;
                $current += $len;
                continue;
            }

            if ($remain > 0) {
                $result .= mb_substr($token, 0, $remain);
            }

            $result .= str_repeat('</>', $open);

            return $result;
        }

        return $result;
    }

    final protected function contentLine(string $content): void
    {
        $visualLength = $this->getVisualLength($content);
        if ($visualLength > $this->boxWidth) {
            $content = $this->truncateWithColorTags($content, $this->boxWidth);
        }

        $currentVisualLength = $this->getVisualLength($content);
        $paddingNeeded = max(0, $this->boxWidth - $currentVisualLength);
        $content = $content.str_repeat(' ', $paddingNeeded);

        $padded = '   '.$content.'   ';
        $this->output->writeln('  │'.$padded.'│  ');
    }

    final protected function f(float|int $n, int $dec = 1): string
    {
        return is_int($n) ? (string) $n : number_format((float) $n, $dec);
    }

    final protected function i(int|float $n): string
    {
        return number_format((int) $n);
    }

    final protected function trim(string $s, int $w): string
    {
        if (mb_strlen($s) <= $w) {
            return $s;
        }

        return rtrim(mb_substr($s, 0, max(0, $w - 1))).'…';
    }

    protected function formatDirectoryPath(string $path): string
    {
        return $path.'/';
    }

    final protected function renderAnalysisFooter(float $score, string $grade): void
    {
        $this->divider('Analysis');
        $this->blank();

        $scoreText = sprintf('<fg=%s>%.0f</>/100', $this->gradeFg($grade), $score);
        $gauge = $this->gauge($score);

        $gradeText = sprintf('Grade: <fg=%s>%s</>', $this->gradeFg($grade), $grade);

        $analysisContent = sprintf('%s            %s            %s', $scoreText, $gauge, $gradeText);

        $visualLength = $this->getVisualLength($analysisContent);
        $totalPadding = max(0, $this->boxWidth - $visualLength);
        $leftPadding = (int) floor($totalPadding / 2);
        $rightPadding = $totalPadding - $leftPadding;

        $analysisLine = str_repeat(' ', $leftPadding).$analysisContent.str_repeat(' ', $rightPadding);
        $this->contentLine($analysisLine);

        $this->blank();
    }

    protected function riskColor(string $risk): string
    {
        $label = strtoupper($risk);

        return match ($risk) {
            'low' => '<fg=green>'.$label.'</>',
            'moderate', 'medium' => '<fg=yellow>'.$label.'</>',
            'high' => '<fg=red>'.$label.'</>',
            'critical' => '<fg=red;options=bold>'.$label.'</>',
            default => '<fg=#cccccc>'.$label.'</>',
        };
    }

    protected function gradeColor(string $grade): string
    {
        return match ($grade) {
            'A+', 'A' => '<fg=green;options=bold>'.$grade.'</>',
            'B' => '<fg=cyan>'.$grade.'</>',
            'C+', 'C' => '<fg=bright-yellow>'.$grade.'</>',
            default => '<fg=red>'.$grade.'</>',
        };
    }

    protected function gradeFg(string $grade): string
    {
        return match ($grade) {
            'A+', 'A' => 'green',
            'B' => 'cyan',
            'C+', 'C' => 'bright-yellow',
            default => 'red',
        };
    }
}
