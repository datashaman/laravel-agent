<?php

namespace DataShaman\LaravelAgent\Tools\Filesystem;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Symfony\Component\Finder\Finder;

class GrepFiles implements Tool
{
    public function __construct(
        protected string $basePath = '',
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Searches file contents using regular expressions.

        - Supports full regex syntax.
        - Output modes: "content" shows matching lines with context, "files_with_matches" shows only file paths (default), "count" shows match counts per file.
        - Filter files with the glob parameter (e.g. "*.php") or type parameter.
        - Use context, before_context, or after_context for surrounding lines in content mode.
        - Use case_insensitive for case-insensitive matching.
        - Use head_limit to cap the number of results.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'pattern' => $schema->string()
                ->description('The regular expression pattern to search for')
                ->required(),
            'path' => $schema->string()
                ->description('File or directory to search in. Defaults to the base path.'),
            'output_mode' => $schema->string()
                ->description('Output mode: "content", "files_with_matches" (default), or "count"')
                ->enum(['content', 'files_with_matches', 'count']),
            'glob' => $schema->string()
                ->description('Glob pattern to filter files (e.g. "*.php", "*.{ts,tsx}")'),
            'case_insensitive' => $schema->boolean()
                ->description('Case insensitive search'),
            'context' => $schema->integer()
                ->description('Number of lines to show before and after each match (content mode only)'),
            'before_context' => $schema->integer()
                ->description('Number of lines to show before each match (content mode only)'),
            'after_context' => $schema->integer()
                ->description('Number of lines to show after each match (content mode only)'),
            'head_limit' => $schema->integer()
                ->description('Limit output to first N results'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $pattern = (string) $request->string('pattern');
        $path = $request['path'] ?? ($this->basePath ?: getcwd());
        $outputMode = $request['output_mode'] ?? 'files_with_matches';
        $glob = $request['glob'] ?? null;
        $caseInsensitive = $request->boolean('case_insensitive');
        $context = $request['context'] ?? 0;
        $beforeContext = $request['before_context'] ?? $context;
        $afterContext = $request['after_context'] ?? $context;
        $headLimit = $request['head_limit'] ?? 0;

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

        $regex = '/'.$pattern.'/'.($caseInsensitive ? 'i' : '');

        // Validate regex
        if (@preg_match($regex, '') === false) {
            return "Invalid regex pattern: {$pattern}";
        }

        $files = $this->getFiles($path, $glob);

        if (empty($files)) {
            return 'No files to search.';
        }

        return match ($outputMode) {
            'content' => $this->searchContent($files, $regex, $beforeContext, $afterContext, $headLimit),
            'count' => $this->searchCount($files, $regex, $headLimit),
            default => $this->searchFilesWithMatches($files, $regex, $headLimit),
        };
    }

    protected function searchFilesWithMatches(array $files, string $regex, int $headLimit): string
    {
        $matches = [];

        foreach ($files as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            if (preg_match($regex, $content)) {
                $matches[] = $file;

                if ($headLimit > 0 && count($matches) >= $headLimit) {
                    break;
                }
            }
        }

        if (empty($matches)) {
            return 'No matches found.';
        }

        return implode("\n", $matches);
    }

    protected function searchContent(array $files, string $regex, int $before, int $after, int $headLimit): string
    {
        $output = '';
        $resultCount = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            $lines = explode("\n", $content);
            $matchLines = [];

            foreach ($lines as $index => $line) {
                if (preg_match($regex, $line)) {
                    $matchLines[] = $index;
                }
            }

            if (empty($matchLines)) {
                continue;
            }

            $output .= "\n{$file}:\n";

            $shownLines = [];

            foreach ($matchLines as $matchIndex) {
                $start = max(0, $matchIndex - $before);
                $end = min(count($lines) - 1, $matchIndex + $after);

                for ($i = $start; $i <= $end; $i++) {
                    if (isset($shownLines[$i])) {
                        continue;
                    }

                    $shownLines[$i] = true;
                    $lineNum = $i + 1;
                    $prefix = $i === $matchIndex ? '>' : ' ';
                    $output .= sprintf("%s%6d\t%s\n", $prefix, $lineNum, $lines[$i]);
                }

                $resultCount++;

                if ($headLimit > 0 && $resultCount >= $headLimit) {
                    break 2;
                }
            }
        }

        if ($output === '') {
            return 'No matches found.';
        }

        return ltrim($output);
    }

    protected function searchCount(array $files, string $regex, int $headLimit): string
    {
        $output = '';
        $entryCount = 0;

        foreach ($files as $file) {
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            $count = preg_match_all($regex, $content);

            if ($count > 0) {
                $output .= "{$file}:{$count}\n";
                $entryCount++;

                if ($headLimit > 0 && $entryCount >= $headLimit) {
                    break;
                }
            }
        }

        if ($output === '') {
            return 'No matches found.';
        }

        return rtrim($output);
    }

    protected function getFiles(string $path, ?string $glob): array
    {
        if (is_file($path)) {
            return [$path];
        }

        if (! is_dir($path)) {
            return [];
        }

        try {
            $finder = new Finder;
            $finder->files()->in($path)->ignoreDotFiles(true);

            if ($glob !== null) {
                $finder->name($glob);
            }

            $files = [];

            foreach ($finder as $file) {
                $files[] = $file->getRealPath();
            }

            return $files;
        } catch (\Exception $e) {
            return [];
        }
    }
}
