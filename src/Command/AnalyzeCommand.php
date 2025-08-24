<?php

declare(strict_types=1);

namespace TwigMetrics\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
use TwigMetrics\Config\ErrorMessages;
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
            ->setHelp(
                <<<'HELP'
                TwigMetrics analyzes Twig templates with a simple, intuitive CLI interface.

                <info>Simple Usage:</info>
                  <info>twigmetrics</info>                          # Default analysis of current directory
                  <info>twigmetrics templates/</info>               # Analyze templates/ directory  
                  <info>twigmetrics complexity</info>               # Complexity analysis on current directory
                  <info>twigmetrics complexity templates/</info>    # Complexity analysis on templates/
                  <info>twigmetrics code-style src/</info>          # Code style analysis on src/

                <info>Available Dimensions:</info>
                  <info>template-files</info>    - Template size, structure, and distribution
                  <info>complexity</info>        - Logical complexity and nesting analysis
                  <info>callables</info>         - Twig functions, filters, macros, variables
                  <info>code-style</info>        - Formatting and naming conventions
                  <info>architecture</info>      - Template roles and reusability patterns
                  <info>maintainability</info>   - Code quality, duplication, tech debt

                <info>Usage Examples:</info>
                  <comment># Quick analysis</comment>
                  <info>twigmetrics</info>                          # Default analysis
                  <info>twigmetrics templates/</info>               # Analyze specific directory
                  
                  <comment># Focused reports</comment>
                  <info>twigmetrics complexity</info>               # Complexity analysis
                  <info>twigmetrics code-style templates/</info>    # Code style for templates/
                  
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
            $io->error(sprintf(ErrorMessages::DIRECTORY_NOT_FOUND, $path));

            return Command::FAILURE;
        }

        $validDimensions = [
            'template-files', 'complexity', /* 'relationships', */ 'callables',
            'code-style', 'architecture', 'maintainability',
        ];

        if ($dimension && !in_array($dimension, $validDimensions, true)) {
            $io->error(sprintf(ErrorMessages::INVALID_DIMENSION, implode(', ', $validDimensions)));

            return Command::FAILURE;
        }

        /** @var ConsoleSectionOutput|null $topSection */
        $topSection = null;
        $headerHelper = new ConsoleOutputHelper($output);
        $headerHelper->writeMainHeader();
        $headerHelper->writeEmptyLine();
        $headerHelper->writeBetaWarning();

        if ($output instanceof ConsoleOutputInterface) {
            $topSection = $output->section();
            $lines = [
                " <info>Path:</info> {$path} ",
            ];
            if ($dimension) {
                $lines[] = " <info>Dimension:</info> {$dimension} ";
            }
            $topSection->writeln($lines);
            $topSection->writeln('');
        } else {
            $output->writeln(" <info>Path:</info> {$path} ");
            if ($dimension) {
                $output->writeln(" <info>Dimension:</info> {$dimension} ");
            }
            $output->writeln('');
        }

        $startTime = microtime(true);

        $templateFinder = new TemplateFinder();
        $files = iterator_to_array($templateFinder->find($path));

        if (empty($files)) {
            $io->error(sprintf(ErrorMessages::NO_TEMPLATES_FOUND, $path));

            return Command::FAILURE;
        }

        $templatesFoundLine = ErrorMessages::consoleInfo(sprintf(ErrorMessages::TEMPLATES_FOUND, count($files)));
        if ($topSection instanceof ConsoleSectionOutput) {
            $topSection->writeln(' '.$templatesFoundLine.' ');
        } else {
            $output->writeln(' '.$templatesFoundLine.' ');
        }

        /** @var ConsoleSectionOutput|null $progressSection */
        $progressSection = null;
        $progressOutput = $output;
        if ($output instanceof ConsoleOutputInterface) {
            $progressSection = $output->section();
            $progressOutput = $progressSection;
        }
        $progressBar = new ProgressBar($progressOutput, count($files));
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
        if ($progressSection instanceof ConsoleSectionOutput) {
            $progressSection->clear();
        } else {
            $output->writeln("\n");
        }
        $results = $batchResult->getTemplateResults();

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $reporter = $this->createReporter($reportType, $dimension);
        $report = $reporter->generate($results);

        $renderer = $this->createRenderer($output);
        $renderer->setHeaderPrinted(true);
        $renderer->setGlobalResults($results);
        if ($topSection instanceof ConsoleSectionOutput) {
            $topSection->clear();
        }
        $renderedOutput = $renderer->render($report);

        $totalLines = 0;
        $total = max(1, count($results));
        $large = $highCx = $deep = 0;
        foreach ($results as $result) {
            $lines = (int) ($result->getMetric('lines') ?? 0);
            $cx = (int) ($result->getMetric('complexity_score') ?? 0);
            $depth = (int) ($result->getMetric('max_depth') ?? 0);
            $totalLines += $lines;
            if ($lines > 200) {
                ++$large;
            }
            if ($cx > 20) {
                ++$highCx;
            }
            if ($depth > 5) {
                ++$deep;
            }
        }
        $penalty = ($highCx / $total) * 40.0 + ($deep / $total) * 25.0 + ($large / $total) * 20.0;
        $score = max(0.0, 100.0 - $penalty);
        $grade = $score >= 95 ? 'A+' : ($score >= 85 ? 'A' : ($score >= 75 ? 'B' : ($score >= 65 ? 'C+' : 'C')));

        $leftPad = '  ';
        $rightPad = '  ';
        $faceWidth = 76;

        $output->writeln('');
        $sep = $leftPad.str_repeat('─', $faceWidth).$rightPad;
        $output->writeln('<fg=gray>'.$sep.'</>');

        $footerText = sprintf('Twig Metrics • %d files • %.2f sec • Grade %s', count($results), $totalTime, $grade);
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
            default => throw new \InvalidArgumentException(sprintf(ErrorMessages::INVALID_DIMENSION_REPORTER, $dimension)),
        };
    }

    private function createRenderer(OutputInterface $output): ConsoleRenderer
    {
        return new ConsoleRenderer($output);
    }

    /**
     * @return array{0: string, 1: string, 2: string|null} [path, reportType, dimension]
     */
    private function parseArguments(string $target, ?string $pathArg): array
    {
        $validDimensions = [
            'template-files', 'complexity', /* 'relationships', */ 'callables',
            'code-style', 'architecture', 'maintainability',
        ];

        if (in_array($target, $validDimensions)) {
            return [$pathArg ?? '.', 'dimension-focused', $target];
        }

        return [$target, 'default', null];
    }
}
