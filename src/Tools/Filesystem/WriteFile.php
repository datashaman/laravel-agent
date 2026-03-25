<?php

namespace DataShaman\LaravelAgent\Tools\Filesystem;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WriteFile implements Tool
{
    public function __construct(
        protected string $basePath = '',
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Writes content to a file, creating it if it doesn't exist or overwriting if it does.

        - Creates parent directories automatically if they don't exist.
        - ALWAYS prefer the EditFile tool for modifying existing files — it only sends the diff.
        - Only use this tool to create new files or for complete rewrites.
        - The file_path must be an absolute path.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()
                ->description('The absolute path to the file to write')
                ->required(),
            'content' => $schema->string()
                ->description('The content to write to the file')
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $filePath = (string) $request->string('file_path');
        $content = (string) $request->string('content');

        if ($error = $this->validatePath($filePath)) {
            return $error;
        }

        $directory = dirname($filePath);

        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true)) {
                return "Failed to create directory: {$directory}";
            }
        }

        $existed = file_exists($filePath);

        if (file_put_contents($filePath, $content) === false) {
            return "Failed to write file: {$filePath}";
        }

        $lines = substr_count($content, "\n") + ($content !== '' && ! str_ends_with($content, "\n") ? 1 : 0);
        $action = $existed ? 'Updated' : 'Created';

        return "{$action} file: {$filePath} ({$lines} lines)";
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

        $checkPath = file_exists($filePath) ? realpath($filePath) : realpath(dirname($filePath));

        if ($checkPath === false) {
            // Directory doesn't exist yet — walk up to find the nearest existing ancestor
            $dir = dirname($filePath);

            while ($dir !== '/' && $dir !== '.' && ! is_dir($dir)) {
                $dir = dirname($dir);
            }

            $resolvedAncestor = realpath($dir);

            if ($resolvedAncestor === false || ! str_starts_with($resolvedAncestor, $realBase)) {
                return "Path is not allowed. Must be within: {$this->basePath}";
            }

            return null;
        }

        if (! str_starts_with($checkPath, $realBase)) {
            return "Path is not allowed. Must be within: {$this->basePath}";
        }

        return null;
    }
}
