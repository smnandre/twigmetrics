<?php

declare(strict_types=1);

namespace TwigMetrics\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TwigMetrics\Analyzer\BatchAnalyzer;
use TwigMetrics\Analyzer\TemplateAnalyzer;
use TwigMetrics\Collector\CodeStyleCollector;
use TwigMetrics\Collector\ComplexityCollector;
use TwigMetrics\Collector\ControlFlowCollector;
use TwigMetrics\Collector\FunctionCollector;
use TwigMetrics\Collector\InheritanceCollector;
use TwigMetrics\Collector\RelationshipCollector;
use TwigMetrics\Renderer\ConsoleRenderer;
use TwigMetrics\Renderer\Helper\ConsoleOutputHelper;
use TwigMetrics\Reporter\Dimension\ArchitectureReporter;
use TwigMetrics\Reporter\Dimension\CodeStyleReporter;
use TwigMetrics\Reporter\Dimension\DimensionReporter;
use TwigMetrics\Reporter\Dimension\LogicalComplexityReporter;
use TwigMetrics\Reporter\Dimension\MaintainabilityReporter;
use TwigMetrics\Reporter\Dimension\TemplateFilesReporter;
use TwigMetrics\Reporter\Dimension\TwigCallablesReporter;
use TwigMetrics\Reporter\SummaryReporter;
use TwigMetrics\Template\Parser\AstTraverser;
use TwigMetrics\Template\Parser\TwigSourceParser;
use TwigMetrics\Template\TemplateFinder;
use TwigMetrics\Template\TwigEnvironmentFactory;

#[AsCommand(
    name: 'analyze',
    description: 'Analyze Twig templates and generate insightful reports'
)]
/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class AnalyzeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'Dimension or path to analyze (default: current directory)', '.')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to analyze when first argument is a dimension')
            ->addOption('dir-depth', 'd', InputOption::VALUE_REQUIRED, 'Directory charts max depth (0 to disable)', '1')
            ->setHelp(<<<'HELP'
TwigMetrics analyzes Twig templates with a simple, intuitive CLI interface.

    <info>twigmetrics</info>                          Analyze current directory
    <info>twigmetrics templates/</info>               Analyze templates/ directory
                                                    
Template size, structure, and distribution
    <info>twigmetrics <comment>template-files</comment> templates/</info>

Logical complexity and nesting analysis  
    <info>twigmetrics <comment>complexity</comment> templates/</info>

Twig functions, filters, macros, variables
    <info>twigmetrics <comment>callables</comment> templates/</info>

Formatting & code style conventions
    <info>twigmetrics <comment>code-style</comment> templates/</info>

Inclusion and inheritance relationships
    <info>twigmetrics <comment>architecture</comment> templates/</info>
    
Code quality, duplication, tech debt
    <info>twigmetrics <comment>maintainability</comment> templates/</info>

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = $input->getArgument('target');
        $pathArg = $input->getArgument('path');

        [$path, $reportType, $dimension] = $this->parseArguments($target, $pathArg);

        if (!is_dir($path)) {
            $io->error(sprintf('Directory not found "%s".', $path));

            return Command::FAILURE;
        }

        $validDimensions = [
            'template-files', 'complexity', 'callables',
            'code-style', 'architecture', 'maintainability',
        ];

        if ($dimension && !in_array($dimension, $validDimensions, true)) {
            $io->error(sprintf('Invalid dimension. Use: %s', implode(', ', $validDimensions)));

            return Command::FAILURE;
        }

        $headerHelper = new ConsoleOutputHelper($output);
        $headerHelper->writeMainHeader();
        $headerHelper->writeEmptyLine();
        $headerHelper->writeBetaWarning();

        $topSection = $this->createSectionOutput($output);
        $lines = [
            " <info>Path:</info> {$path} ",
        ];
        if ($dimension) {
            $lines[] = " <info>Dimension:</info> {$dimension} ";
        }
        $topSection->writeln($lines);
        $topSection->writeln('');

        $startTime = microtime(true);

        $templateFinder = new TemplateFinder();
        $files = iterator_to_array($templateFinder->find($path));

        if (empty($files)) {
            $io->error(sprintf('No Twig templates found in: %s', $path));

            return Command::FAILURE;
        }

        $templatesFoundLine = sprintf('Found %d template(s) to analyze...', count($files));
        $topSection->writeln(' <info>'.$templatesFoundLine.'</info> ');

        $progressSection = $this->createSectionOutput($output);
        $progressBar = new ProgressBar($progressSection, count($files));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        $twigEnvironment = TwigEnvironmentFactory::createForAnalysis();
        $parser = new TwigSourceParser($twigEnvironment);

        $collectors = [
            'complexity' => new ComplexityCollector(),
            'function' => new FunctionCollector(),
            'inheritance' => new InheritanceCollector(),
            'control_flow' => new ControlFlowCollector(),
            'relationships' => new RelationshipCollector(),
            'code_style' => new CodeStyleCollector(),
        ];

        $traverser = new AstTraverser();
        $templateAnalyzer = new TemplateAnalyzer($collectors, $traverser, $parser);
        $batchAnalyzer = new BatchAnalyzer($templateAnalyzer);

        $batchResult = $batchAnalyzer->analyze($files);

        $progressBar->finish();
        $this->clearSection($progressSection, $output);
        $results = $batchResult->getTemplateResults();

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $reporter = $this->createReporter($reportType, $dimension);
        $report = $reporter->generate($results);

        $dirDepthOpt = (string) ($input->getOption('dir-depth') ?? '1');
        $dirDepth = 1;
        if (is_numeric($dirDepthOpt)) {
            $dirDepth = (int) $dirDepthOpt;
        } elseif (in_array(strtolower($dirDepthOpt), ['false', 'off', 'no'], true)) {
            $dirDepth = 0;
        }

        $renderer = $this->createRenderer($output, $dirDepth);
        if ('dimension-focused' === $reportType && $dimension) {
            $renderer->setActiveDimension($dimension);
        }
        $renderer->setHeaderPrinted(true);
        $renderer->setGlobalResults($results);
        $this->clearSection($topSection, $output);
        $renderedOutput = $renderer->render($report);

        $leftPad = '  ';
        $rightPad = '  ';
        $faceWidth = 76;

        $output->writeln('');
        $sep = $leftPad.str_repeat('─', $faceWidth).$rightPad;
        $output->writeln('<fg=gray>'.$sep.'</>');

        $peakMemMb = memory_get_peak_usage(true) / (1024 * 1024);
        $footerText = sprintf('Twig Metrics • %d files • %.2f sec • %.1f MB', count($results), $totalTime, $peakMemMb);
        $len = mb_strlen($footerText);
        $left = (int) floor(max(0, ($faceWidth - $len) / 2));
        $right = max(0, $faceWidth - $left - $len);
        $footerFace = str_repeat(' ', $left).$footerText.str_repeat(' ', $right);
        $output->writeln($leftPad.'<fg=gray>'.$footerFace.'</>'.$rightPad);
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function createReporter(string $reportType, ?string $dimension): SummaryReporter|DimensionReporter
    {
        return match ($reportType) {
            'dimension-focused' => $this->createDimensionReporter($dimension),
            default => new SummaryReporter(),
        };
    }

    private function createDimensionReporter(string $dimension): DimensionReporter
    {
        return match ($dimension) {
            'template-files' => new TemplateFilesReporter(),
            'complexity' => new LogicalComplexityReporter(),
            'callables' => new TwigCallablesReporter(),
            'code-style' => new CodeStyleReporter(),
            'architecture' => new ArchitectureReporter(),
            'maintainability' => new MaintainabilityReporter(),
            default => throw new \InvalidArgumentException(sprintf('Invalid dimension: %s', $dimension)),
        };
    }

    private function createRenderer(OutputInterface $output, int $dirDepth): ConsoleRenderer
    {
        return new ConsoleRenderer($output, $dirDepth);
    }

    /**
     * Create a console section if supported, otherwise return the original output.
     */
    private function createSectionOutput(OutputInterface $output): OutputInterface
    {
        if ($output instanceof ConsoleOutputInterface) {
            return $output->section();
        }

        return $output;
    }

    /**
     * Clear a section if possible, otherwise add a newline to keep spacing readable.
     */
    private function clearSection(OutputInterface $section, OutputInterface $fallbackOutput): void
    {
        if ($section instanceof ConsoleSectionOutput) {
            $section->clear();
        } else {
            $fallbackOutput->writeln("\n");
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string|null} [path, reportType, dimension]
     */
    private function parseArguments(string $target, ?string $pathArg): array
    {
        $norm = $this->normalizeDimensionSlug($target);
        if (null !== $norm) {
            return [$pathArg ?? '.', 'dimension-focused', $norm];
        }

        return [$target, 'default', null];
    }

    private function normalizeDimensionSlug(?string $slug): ?string
    {
        if (null === $slug) {
            return null;
        }

        $s = strtolower($slug);

        $canonical = [
            'template-files' => ['template-files', 'templates', 'files', 'tpl', 'tfiles'],
            'code-style' => ['code-style', 'codestyle', 'cs', 'style'],
            'complexity' => ['complexity', 'comp', 'complex', 'logic'],
            'callables' => ['callables', 'call', 'function', 'functions', 'filter', 'filters', 'callable'],
            'architecture' => ['architecture', 'archi', 'arch'],
            'maintainability' => ['maintainability', 'dept', 'maintain', 'maintenance'],
        ];

        foreach ($canonical as $canon => $aliases) {
            if (in_array($s, $aliases, true)) {
                return $canon;
            }
        }

        return null;
    }
}
