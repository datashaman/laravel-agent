<?php

namespace DataShaman\LaravelAgent\Tools\Filesystem;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadFile implements Tool
{
    public function __construct(
        protected string $basePath = '',
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Reads a file from the filesystem and returns its contents with line numbers.

        - The file_path parameter must be an absolute path.
        - By default, reads up to 2000 lines from the beginning of the file.
        - Use offset and limit parameters to read specific line ranges for large files.
        - Returns line numbers starting at 1.
        - Detects binary files and returns a message instead of binary content.
        - You should read a file before attempting to edit it.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()
                ->description('The absolute path to the file to read')
                ->required(),
            'offset' => $schema->integer()
                ->description('The line number to start reading from (1-based). Only provide if the file is too large to read at once.'),
            'limit' => $schema->integer()
                ->description('The number of lines to read. Defaults to 2000. Only provide if the file is too large to read at once.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $filePath = (string) $request->string('file_path');
        $offset = $request['offset'] ?? 1;
        $limit = $request['limit'] ?? 2000;

        if ($error = $this->validatePath($filePath)) {
            return $error;
        }

        if (! file_exists($filePath)) {
            return "File does not exist: {$filePath}";
        }

        if (! is_readable($filePath)) {
            return "File is not readable: {$filePath}";
        }

        if ($this->isBinary($filePath)) {
            $size = filesize($filePath);

            return "Binary file detected ({$size} bytes): {$filePath}. Cannot display binary content.";
        }

        $lines = file($filePath);

        if ($lines === false) {
            return "Failed to read file: {$filePath}";
        }

        $totalLines = count($lines);

        if ($totalLines === 0) {
            return "File is empty: {$filePath}";
        }

        $startIndex = max(0, $offset - 1);
        $slice = array_slice($lines, $startIndex, $limit);

        $output = '';
        $lineNum = $startIndex + 1;

        foreach ($slice as $line) {
            $output .= sprintf("%6d\t%s", $lineNum, $line);
            $lineNum++;
        }

        if ($startIndex + $limit < $totalLines) {
            $remaining = $totalLines - ($startIndex + $limit);
            $output .= "\n... {$remaining} more lines. Use offset/limit to read more.";
        }

        return $output;
    }

    protected function validatePath(string $filePath): ?string
    {
        if ($this->basePath === '') {
            return null;
        }

        $realBase = realpath($this->basePath);

        if ($realBase === false) {
            return "Base path does not exist: {$this->basePath}";
        }

        // For nonexistent files, validate the directory
        $checkPath = file_exists($filePath) ? realpath($filePath) : realpath(dirname($filePath));

        if ($checkPath === false || ! str_starts_with($checkPath, $realBase)) {
            return "Path is not allowed. Must be within: {$this->basePath}";
        }

        return null;
    }

    protected function isBinary(string $filePath): bool
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return false;
        }

        $chunk = fread($handle, 8192);
        fclose($handle);

        if ($chunk === false || $chunk === '') {
            return false;
        }

        return str_contains($chunk, "\0");
    }
}
