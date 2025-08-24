<?php

declare(strict_types=1);

namespace TwigMetrics\Console\Helper;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class DualOutputHelper
{
    private BufferedOutput $left;
    private BufferedOutput $right;
    private SymfonyStyle $leftIo;
    private SymfonyStyle $rightIo;

    public function __construct(
        private readonly OutputInterface $finalOutput,
        private readonly int $spacing = 4,
        private readonly int $totalWidth = 80,
    ) {
        $this->left = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $this->right = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

        $this->leftIo = new SymfonyStyle(new ArgvInput(), $this->left);
        $this->rightIo = new SymfonyStyle(new ArgvInput(), $this->right);
    }

    public function getLeftCol(): SymfonyStyle
    {
        return $this->leftIo;
    }

    public function getRightCol(): SymfonyStyle
    {
        return $this->rightIo;
    }

    public function render(): void
    {
        $leftBuffer = rtrim($this->left->fetch(), "\n");
        $rightBuffer = rtrim($this->right->fetch(), "\n");
        $leftLines = explode("\n", $leftBuffer);
        $rightLines = explode("\n", $rightBuffer);

        $leftWidth = (int) floor(($this->totalWidth - $this->spacing) / 2);
        $rightWidth = $this->totalWidth - $this->spacing - $leftWidth;

        $maxLines = max(count($leftLines), count($rightLines));
        $leftLines = array_pad($leftLines, $maxLines, '');
        $rightLines = array_pad($rightLines, $maxLines, '');

        for ($i = 0; $i < $maxLines; ++$i) {
            $left = $this->clipAndPad($leftLines[$i], $leftWidth);
            $right = $this->clipAndPad($rightLines[$i], $rightWidth);
            $this->finalOutput->writeln($left.str_repeat(' ', $this->spacing).$right);
        }
    }

    private function clipAndPad(string $text, int $width): string
    {
        $stripped = $this->stripAnsi($text);
        $trimmed = mb_strimwidth($stripped, 0, $width, '');
        $delta = $width - mb_strwidth($trimmed);

        if ($stripped !== $text) {
            $text = preg_replace('/'.preg_quote($stripped, '/').'/', $trimmed, $text, 1) ?? $trimmed;
        } else {
            $text = $trimmed;
        }

        return $text.str_repeat(' ', max(0, $delta));
    }

    private function stripAnsi(string $text): string
    {
        return preg_replace('/\e\\[[0-9;]*m/', '', $text) ?? '';
    }
}
