<?php

namespace TypesenseSearch\Helper;

/**
 * Class PdfToText
 *
 * Wraps the system pdftotext binary.
 *
 * Responsibilities:
 *   - Detect whether the binary is available on the server.
 *   - Resolve the absolute path to the binary.
 *   - Extract plain text from a PDF file.
 *
 * Usage:
 *   if (PdfToText::isAvailable()) {
 *       $text = PdfToText::extractText('/path/to/file.pdf');
 *   }
 *
 * @package TypesenseSearch\Helper
 */
class PdfToText
{
    /**
     * Known binary locations to check as a hard-coded fallback when PATH
     * resolution fails (common when PHP runs under a restricted web-server
     * user that has a minimal environment).
     */
    private const CANDIDATES = [
        '/usr/bin/pdftotext',
        '/usr/local/bin/pdftotext',
        '/opt/homebrew/bin/pdftotext',
        '/opt/local/bin/pdftotext',
        '/bin/pdftotext',
    ];

    /**
     * Cached result of binary resolution.
     *  - null  = not yet resolved
     *  - false = binary not found
     *  - string = absolute path to binary
     *
     * @var string|null|false
     */
    private static string|null|false $binaryPath = null;

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * Return true if the pdftotext binary can be found and executed.
     */
    public static function isAvailable(): bool
    {
        return self::getBinaryPath() !== false;
    }

    /**
     * Return the resolved absolute path to the pdftotext binary, or false
     * if it cannot be found.
     */
    public static function getBinaryPath(): string|false
    {
        if (self::$binaryPath !== null) {
            return self::$binaryPath;
        }

        // Strategy 1 — login shell.
        // Spawning a login shell ($SHELL -l) mimics the interactive session
        // PATH (e.g. Homebrew on macOS adds /opt/homebrew/bin only for login
        // shells). This is the most reliable strategy for macOS/Linux dev boxes.
        $shellBinary = self::detectShell();
        if ($shellBinary !== null) {
            $output     = [];
            $returnCode = 0;
            exec(escapeshellcmd($shellBinary) . ' -lc "command -v pdftotext" 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return self::$binaryPath = trim($output[0]);
            }
        }

        // Strategy 2 — which.
        // Simpler, widely available, but respects only the current PATH.
        $output     = [];
        $returnCode = 0;
        exec('which pdftotext 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return self::$binaryPath = trim($output[0]);
        }

        // Strategy 3 — hard-coded paths.
        // Covers the case where pdftotext is installed in a standard location
        // but neither of the above resolved it (rare, but happens with some
        // Docker images and minimal server setups).
        foreach (self::CANDIDATES as $path) {
            if (is_executable($path)) {
                return self::$binaryPath = $path;
            }
        }

        // Nothing worked — write diagnostics if WP_DEBUG is on so developers
        // can inspect the precise environment the web-server user sees.
        self::writeDebugLog();

        return self::$binaryPath = false;
    }

    /**
     * Extract plain text from a PDF file.
     *
     * @param string               $filePath Absolute path to the PDF file.
     * @param array<string, mixed> $options  {
     *   @type int    $firstPage  First page to extract (1-based). Omit to start from page 1.
     *   @type int    $lastPage   Last page to extract. Omit to extract all pages.
     *   @type string $encoding   Output character encoding. Default: 'UTF-8'.
     *   @type bool   $layout     Preserve the original physical layout. Default: false.
     * }
     * @return string|false Extracted text on success, false on failure (binary
     *                      not found, file not readable, or non-zero exit code).
     */
    public static function extractText(string $filePath, array $options = []): string|false
    {
        $binary = self::getBinaryPath();
        if ($binary === false) {
            return false;
        }

        if (!is_readable($filePath)) {
            return false;
        }

        $args = self::buildArgs($options);

        // Pass '-' as the output file so pdftotext writes to stdout.
        $cmd = sprintf(
            '%s %s %s -',
            escapeshellcmd($binary),
            implode(' ', $args),
            escapeshellarg($filePath)
        );

        $output     = [];
        $returnCode = 0;
        exec($cmd . ' 2>/dev/null', $output, $returnCode);

        if ($returnCode !== 0) {
            return false;
        }

        return implode(PHP_EOL, $output);
    }

    /**
     * Reset the cached binary path.
     *
     * Useful in tests or when pdftotext is installed at runtime and you need
     * to re-detect it without reloading PHP.
     */
    public static function reset(): void
    {
        self::$binaryPath = null;
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    /**
     * Detect the user's default login shell from the SHELL environment
     * variable, falling back to common paths.
     */
    private static function detectShell(): ?string
    {
        $shell = getenv('SHELL');
        if ($shell && is_executable((string) $shell)) {
            return $shell;
        }

        foreach (['/bin/bash', '/usr/bin/bash', '/bin/sh'] as $fallback) {
            if (is_executable($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * Build the pdftotext argument list from the options array.
     *
     * @param  array<string, mixed> $options
     * @return string[]
     */
    private static function buildArgs(array $options): array
    {
        $args = [];

        if (!empty($options['firstPage'])) {
            $args[] = '-f ' . (int) $options['firstPage'];
        }

        if (!empty($options['lastPage'])) {
            $args[] = '-l ' . (int) $options['lastPage'];
        }

        $encoding = !empty($options['encoding']) ? (string) $options['encoding'] : 'UTF-8';
        $args[]   = '-enc ' . escapeshellarg($encoding);

        if (!empty($options['layout'])) {
            $args[] = '-layout';
        }

        return $args;
    }

    /**
     * Append a diagnostic snapshot to the system temp dir so a developer /
     * sysadmin can inspect what the web-server PHP process can see.
     * Only runs when WP_DEBUG is true.
     */
    private static function writeDebugLog(): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $entry = print_r([
            'timestamp'          => date('Y-m-d H:i:s'),
            'php_sapi'           => PHP_SAPI,
            'user'               => function_exists('posix_getpwuid')
                                        ? posix_getpwuid(posix_geteuid())
                                        : get_current_user(),
            'env_path'           => getenv('PATH'),
            'candidates_checked' => self::CANDIDATES,
        ], true);

        @file_put_contents(
            sys_get_temp_dir() . '/typesense_pdftotext_debug.log',
            $entry . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
