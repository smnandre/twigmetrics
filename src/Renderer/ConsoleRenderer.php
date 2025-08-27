<?php

declare(strict_types=1);

namespace TwigMetrics\Renderer;

use Symfony\Component\Console\Output\OutputInterface;
use TwigMetrics\Analyzer\AnalysisResult;
use TwigMetrics\Renderer\Dimension\DimensionRendererFactory;
use TwigMetrics\Renderer\Helper\ConsoleOutputHelper;
use TwigMetrics\Report\Report;
use TwigMetrics\Report\Section\ReportSection;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class ConsoleRenderer implements RendererInterface
{
    private ConsoleOutputHelper $outputHelper;
    private bool $headerPrinted = false;

    /**
     * @var array<AnalysisResult>
     */
    private array $globalResults = [];
    private int $maxDepth = 1;
    private ?string $activeDimension = null;

    public function __construct(
        private OutputInterface $output,
        int $maxDepth = 1,
    ) {
        $this->maxDepth = $maxDepth;
        $this->outputHelper = new ConsoleOutputHelper($output);
    }

    public function setHeaderPrinted(bool $printed): void
    {
        $this->headerPrinted = $printed;
    }

    public function setActiveDimension(?string $dimensionSlug): void
    {
        $this->activeDimension = $dimensionSlug;
    }

    /**
     * @param AnalysisResult[] $results
     */
    public function setGlobalResults(array $results): void
    {
        $this->globalResults = $results;
    }

    private function renderDimensionVisualRecap(ReportSection $section): void
    {
        $data = $section->getData();
        $dimensions = $data['dimensions'] ?? [];
        $overall = $data['overall'] ?? null;

        if (empty($dimensions)) {
            return;
        }

        $this->renderImprovedDimensionCards($dimensions, is_array($overall) ? $overall : null);
    }

    public function render(Report $report): string
    {
        if (!$this->headerPrinted) {
            $this->outputHelper->writeHeader();
        }

        $sections = $report->getSections();

        $deferredVisualRecap = null;

        foreach ($sections as $section) {
            $currentSection = $section;
            if ('dimension_visual_recap' === $currentSection->getType()) {
                $deferredVisualRecap = $currentSection;
                break;
            }
        }

        $this->renderDimensionalAnalysisBlocks();

        if ($deferredVisualRecap instanceof ReportSection) {
            $this->renderDimensionVisualRecap($deferredVisualRecap);
        }

        return '';
    }

    private function getTextLengthWithoutAnsi(string $text): int
    {
        $cleanText = preg_replace('/\e\[[0-9;]*m/', '', $text) ?? '';
        $cleanText = preg_replace('/<[^>]+>/', '', $cleanText) ?? '';

        return mb_strlen($cleanText);
    }

    /**
     * @param array<string, mixed>      $dimensionScores
     * @param array<string, mixed>|null $overall
     */
    public function renderImprovedDimensionCards(array $dimensionScores, ?array $overall = null): void
    {
        $dimensionGroups = array_chunk($dimensionScores, 3, true);
        foreach ($dimensionGroups as $i => $group) {
            $showMini = 0 !== $i;
            $this->renderImprovedDimensionRow($group, $showMini);
            if (0 === $i && count($dimensionGroups) > 1) {
                $this->output->writeln('');
            }
        }

        if (null !== $overall) {
            $this->output->writeln('');

            $score = (int) ($overall['score'] ?? 0);
            $grade = (string) ($overall['grade'] ?? $this->scoreToGrade($score));
            $color = $this->getScoreColor($score);
            $text = sprintf('<fg=%s;options=bold>Final Grade: %s (%d/100)</fg=%s;options=bold>', $color, $grade, $score, $color);
            $this->output->writeln($this->centerFaceLine('  '.$text));
        }
    }

    /**
     * @param array<string, mixed> $dimensions
     */
    private function renderImprovedDimensionRow(array $dimensions, bool $showMini = true): void
    {
        $cardWidth = 24;
        $spacing = 2;

        $topLine = '  ';
        foreach ($dimensions as $name => $data) {
            $titleTruncated = strlen($name) > 15 ? substr($name, 0, 12).'...' : $name;
            $titlePadding = $cardWidth - strlen($titleTruncated) - 5;
            $topLine .= sprintf('<fg=#ddd>╭─</> %s <fg=#ddd>%s╮</>', $titleTruncated, str_repeat('─', max(0, $titlePadding)));
            if ($name !== array_key_last($dimensions)) {
                $topLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln($this->centerFaceLine($topLine));

        $contentLine = '  ';
        foreach ($dimensions as $name => $data) {
            $score = $data['score'] ?? 0;
            $grade = $data['grade'] ?? $this->scoreToGrade($score);
            $dotsIndicator = $this->getDotsIndicator($score);
            $coloredGrade = $this->getColoredGradeWithScore($grade, $score);

            $content = sprintf('<fg=#ddd>│</>  %s      %s <fg=#ddd>│</>', $dotsIndicator, $coloredGrade);
            $contentLine .= $content;

            if ($name !== array_key_last($dimensions)) {
                $contentLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln($this->centerFaceLine($contentLine));

        $hasMini = false;
        if ($showMini) {
            foreach ($dimensions as $data) {
                if (!empty($data['mini'])) {
                    $hasMini = true;
                    break;
                }
            }
        }
        if ($hasMini) {
            $miniLine = '  ';
            foreach ($dimensions as $name => $data) {
                $miniText = (string) ($data['mini'] ?? '');
                $innerWidth = $cardWidth - 2;

                $miniText = ' '.mb_strimwidth(strip_tags($miniText), 0, $innerWidth - 1, '…');
                $miniText = str_pad($miniText, $innerWidth, ' ');
                $miniLine .= '│'.$miniText.'│';
                if ($name !== array_key_last($dimensions)) {
                    $miniLine .= str_repeat(' ', $spacing);
                }
            }
            $this->output->writeln('<fg=#ddd>'.$this->centerFaceLine($miniLine).'</>');
        }

        $bottomLine = '  ';
        foreach ($dimensions as $name => $data) {
            $bottomLine .= '╰'.str_repeat('─', $cardWidth - 2).'╯';
            if ($name !== array_key_last($dimensions)) {
                $bottomLine .= str_repeat(' ', $spacing);
            }
        }
        $this->output->writeln('<fg=#ddd>'.$this->centerFaceLine($bottomLine).'</>');
    }

    private function centerFaceLine(string $line): string
    {
        $faceWidth = 76;
        $visibleLen = $this->getTextLengthWithoutAnsi($line) - 2;
        $visibleLen = max(0, $visibleLen);
        $left = (int) floor(max(0, ($faceWidth - $visibleLen) / 2));
        $right = max(0, $faceWidth - $visibleLen - $left);
        $content = substr($line, 2);

        return '  '.str_repeat(' ', $left).$content;
    }

    private function getDotsIndicator(int $score): string
    {
        $filledDots = min(6, max(0, (int) round($score / 100 * 6)));
        $emptyDots = 6 - $filledDots;

        $color = $this->getScoreColor($score);

        $filled = str_repeat("<fg={$color}>●</fg={$color}> ", $filledDots);
        $empty = str_repeat('<fg=#cccccc>○</fg=#cccccc> ', $emptyDots);

        return trim($filled.$empty);
    }

    private function getColoredGradeWithScore(string $grade, int $score): string
    {
        $color = $this->getScoreColor($score);

        $displayGrade = $score >= 95 ? 'A+' : ($grade.' ');

        return "<fg={$color};options=bold>{$displayGrade}</fg={$color};options=bold>";
    }

    private function getScoreColor(int $score): string
    {
        return match (true) {
            $score >= 90 => 'green',
            $score >= 80 => 'cyan',
            $score >= 70 => 'yellow',
            $score >= 60 => 'magenta',
            $score >= 50 => 'red',
            default => 'red',
        };
    }

    private function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }

    /**
     * Renders the 6 dimensional analysis blocks.
     */
    public function renderDimensionalAnalysisBlocks(): void
    {
        $this->output->writeln('');
        $this->output->writeln('');
        $slug = $this->activeDimension;
        $factory = new DimensionRendererFactory();

        if (null === $slug) {
            $dimensions = ['template-files', 'code-style', 'callables', 'architecture', 'complexity', 'maintainability'];

            foreach ($dimensions as $dimension) {
                $presenter = $factory->createPresenter($dimension);
                $renderer = $factory->createRenderer($dimension, $this->output);
                $data = $presenter->present($this->globalResults, $this->maxDepth, $this->maxDepth > 0);
                $renderer->render($data);
                $this->output->writeln('');
            }

            return;
        }

        $presenter = $factory->createPresenter($slug);
        $renderer = $factory->createRenderer($slug, $this->output);
        $data = $presenter->present($this->globalResults, $this->maxDepth, $this->maxDepth > 0);
        $renderer->render($data);
    }
}
