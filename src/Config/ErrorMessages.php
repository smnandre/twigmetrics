<?php

declare(strict_types=1);

namespace TwigMetrics\Config;

/**
 * Standardized error messages for consistent user experience.
 *
 * This class contains all error messages used throughout the application,
 * ensuring consistent formatting and language across all commands.
 *
 * @author Simon André <smn.andre@gmail.com>
 */
final class ErrorMessages
{
    /** Error when specified directory does not exist */
    public const DIRECTORY_NOT_FOUND = 'Directory not found: %s';

    /** Error when no Twig templates found in directory */
    public const NO_TEMPLATES_FOUND = 'No Twig templates found in: %s';

    /** Error when invalid output format is specified */
    public const INVALID_FORMAT = 'Invalid format. Use: %s';

    /** Error when invalid analysis dimension is specified */
    public const INVALID_DIMENSION = 'Invalid dimension. Use: %s';

    /** Exception message for invalid dimension in reporter */
    public const INVALID_DIMENSION_REPORTER = 'Invalid dimension: %s';

    /** Error when unable to read template file */
    public const UNABLE_TO_READ_FILE = 'Unable to read template file: %s';

    /** Error when unable to write output file */
    public const UNABLE_TO_WRITE_FILE = 'Unable to write output file: %s';

    /** Error when template parsing fails */
    public const TEMPLATE_PARSE_ERROR = 'Failed to parse template %s: %s';

    /** Warning when analysis fails for a template */
    public const ANALYSIS_FAILED = 'Analysis failed for template %s: %s';

    /** Error when no analyzers are configured */
    public const NO_ANALYZERS_CONFIGURED = 'No analyzers have been configured';

    /** Error when batch analysis fails completely */
    public const BATCH_ANALYSIS_FAILED = 'Batch analysis failed: %s';

    /** Error when invalid configuration option is provided */
    public const INVALID_CONFIGURATION = 'Invalid configuration option: %s';

    /** Error when required configuration is missing */
    public const MISSING_CONFIGURATION = 'Missing required configuration: %s';

    /** Error when configuration file cannot be loaded */
    public const CONFIGURATION_LOAD_ERROR = 'Failed to load configuration file: %s';

    /** Error when output rendering fails */
    public const RENDER_ERROR = 'Failed to render output: %s';

    /** Error when unsupported output format is requested */
    public const UNSUPPORTED_FORMAT = 'Unsupported output format: %s';

    /** Generic error for unexpected failures */
    public const UNEXPECTED_ERROR = 'An unexpected error occurred: %s';

    /** Error when required dependency is missing */
    public const MISSING_DEPENDENCY = 'Missing required dependency: %s';

    /** Error when memory limit is exceeded */
    public const MEMORY_LIMIT_EXCEEDED = 'Memory limit exceeded during analysis. Consider analyzing fewer templates or increasing PHP memory limit.';

    /** Success message when report is saved to file */
    public const REPORT_SAVED = 'Report saved to: %s';

    /** Success message when analysis completes */
    public const ANALYSIS_COMPLETED = 'Analysis completed in %.2fs';

    /** Info message showing number of templates found */
    public const TEMPLATES_FOUND = 'Found %d template(s) to analyze...';

    /** Info message showing analysis progress */
    public const ANALYSIS_PROGRESS = 'Analyzing %d templates...';

    /**
     * Format an error message with parameters.
     *
     * @param string $message The error message template
     * @param mixed  ...$args Arguments to format into the message
     *
     * @return string The formatted error message
     */
    public static function format(string $message, ...$args): string
    {
        return sprintf($message, ...$args);
    }

    /**
     * Create a console error message with proper styling.
     *
     * @param string $message The error message
     *
     * @return string The styled error message for console output
     */
    public static function consoleError(string $message): string
    {
        return "<error>{$message}</error>";
    }

    /**
     * Create a console info message with proper styling.
     *
     * @param string $message The info message
     *
     * @return string The styled info message for console output
     */
    public static function consoleInfo(string $message): string
    {
        return "<info>{$message}</info>";
    }

    /**
     * Create a console comment message with proper styling.
     *
     * @param string $message The comment message
     *
     * @return string The styled comment message for console output
     */
    public static function consoleComment(string $message): string
    {
        return "<comment>{$message}</comment>";
    }
}
