<?php

namespace DataShaman\LaravelAgent\Tools\Filesystem;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Symfony\Component\Finder\Finder;

class GlobFiles implements Tool
{
    public function __construct(
        protected string $basePath = '',
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Fast file pattern matching tool that finds files by name patterns.

        - Supports glob patterns like "**/*.php" or "src/**/*.ts".
        - Returns matching file paths sorted by modification time (most recent first).
        - Use this tool when you need to find files by name patterns.
        - For searching file contents, use GrepFiles instead.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()
                ->description('The glob pattern to match files against (e.g. "**/*.php", "src/**/*.ts")')
                ->required(),
            'path' => $schema->string()
                ->description('The directory to search in. Defaults to the base path.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $pattern = (string) $request->string('pattern');
        $path = $request['path'] ?? ($this->basePath ?: getcwd());

        if ($this->basePath !== '') {
            $realBase = realpath($this->basePath);
            $realPath = realpath($path);

            if ($realBase === false) {
                return "Base path does not exist: {$this->basePath}";
            }

            if ($realPath === false || ! str_starts_with($realPath, $realBase)) {
                return "Path is not allowed. Must be within: {$this->basePath}";
            }
        }

        if (! is_dir($path)) {
            return "Directory does not exist: {$path}";
        }

        $files = $this->findFiles($path, $pattern);

        if (empty($files)) {
            return "No files matched the pattern: {$pattern}";
        }

        // Sort by modification time, most recent first
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return implode("\n", $files);
    }

    protected function findFiles(string $directory, string $pattern): array
    {
        // Handle ** patterns by splitting into directory and filename parts
        $parts = explode('/', $pattern);
        $hasRecursive = false;
        $namePattern = array_pop($parts);
        $subDir = '';

        foreach ($parts as $part) {
            if ($part === '**') {
                $hasRecursive = true;
            } else {
                $subDir .= '/'.$part;
            }
        }

        $searchDir = rtrim($directory, '/').($subDir ? '/'.ltrim($subDir, '/') : '');

        if (! is_dir($searchDir)) {
            return [];
        }

        try {
            $finder = new Finder;
            $finder->files()->in($searchDir)->name($namePattern);

            if (! $hasRecursive && $subDir === '' && ! str_contains($pattern, '/')) {
                $finder->depth(0);
            }

            $files = [];

            foreach ($finder as $file) {
                $files[] = $file->getRealPath();
            }

            return $files;
        } catch (\Exception $e) {
            // Fallback to native glob for simple patterns
            $fullPattern = rtrim($directory, '/').'/'.$pattern;
            $results = glob($fullPattern, GLOB_BRACE);

            return $results ?: [];
        }
    }
}
