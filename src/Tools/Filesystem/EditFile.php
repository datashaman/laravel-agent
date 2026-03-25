<?php

namespace DataShaman\LaravelAgent\Tools\Filesystem;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditFile implements Tool
{
    public function __construct(
        protected string $basePath = '',
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Performs exact string replacements in files.

        - The edit will FAIL if old_string is not unique in the file. Provide more surrounding context to make it unique, or use replace_all to change every instance.
        - old_string and new_string must be different.
        - old_string must not be empty.
        - Preserves exact indentation — match the whitespace precisely.
        - Use replace_all for renaming variables or strings across the file.
        - ALWAYS prefer this tool over WriteFile for modifying existing files.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file_path' => $schema->string()
                ->description('The absolute path to the file to modify')
                ->required(),
            'old_string' => $schema->string()
                ->description('The exact text to find and replace')
                ->required(),
            'new_string' => $schema->string()
                ->description('The text to replace it with')
                ->required(),
            'replace_all' => $schema->boolean()
                ->description('Replace all occurrences instead of requiring uniqueness. Default: false'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $filePath = (string) $request->string('file_path');
        $oldString = (string) $request->string('old_string');
        $newString = (string) $request->string('new_string');
        $replaceAll = $request->boolean('replace_all');

        if ($error = $this->validatePath($filePath)) {
            return $error;
        }

        if (! file_exists($filePath)) {
            return "File does not exist: {$filePath}";
        }

        if ($oldString === '' || $oldString === null) {
            return 'old_string must not be empty.';
        }

        if ($oldString === $newString) {
            return 'old_string and new_string are identical. No change would be made.';
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return "Failed to read file: {$filePath}";
        }

        $count = substr_count($content, $oldString);

        if ($count === 0) {
            return $this->notFoundMessage($content, $oldString, $filePath);
        }

        if ($count > 1 && ! $replaceAll) {
            $lineNumbers = $this->findLineNumbers($content, $oldString);
            $lines = implode(', ', $lineNumbers);

            return "Found {$count} occurrences of old_string (lines {$lines}). Provide more surrounding context to make it unique, or use replace_all to change every instance.";
        }

        $newContent = $replaceAll
            ? str_replace($oldString, $newString, $content)
            : $this->replaceFirst($content, $oldString, $newString);

        if (file_put_contents($filePath, $newContent) === false) {
            return "Failed to write file: {$filePath}";
        }

        if ($replaceAll && $count > 1) {
            return "Replaced {$count} occurrences in {$filePath}.";
        }

        return "Successfully edited {$filePath}.";
    }

    protected function notFoundMessage(string $content, string $oldString, string $filePath): string
    {
        $lines = explode("\n", $content);
        $suggestions = [];
        $oldTrimmed = trim($oldString);
        $oldLower = mb_strtolower($oldTrimmed);

        foreach ($lines as $index => $line) {
            $lineNum = $index + 1;
            $trimmedLine = trim($line);

            // Check for case-insensitive match
            if (mb_strtolower($trimmedLine) === $oldLower && $trimmedLine !== $oldTrimmed) {
                $suggestions[] = "  Line {$lineNum}: case difference — '{$trimmedLine}'";
                continue;
            }

            // Check for whitespace difference
            if ($trimmedLine === $oldTrimmed && $line !== $oldString) {
                $suggestions[] = "  Line {$lineNum}: whitespace difference — '{$line}'";
                continue;
            }

            // Check for similar strings using levenshtein on short strings
            if (strlen($oldTrimmed) < 100 && strlen($trimmedLine) < 100 && strlen($trimmedLine) > 0) {
                $distance = levenshtein($oldTrimmed, $trimmedLine);
                $threshold = max(3, (int) (strlen($oldTrimmed) * 0.3));

                if ($distance > 0 && $distance <= $threshold) {
                    $suggestions[] = "  Line {$lineNum}: similar — '{$trimmedLine}'";
                }
            }
        }

        $message = "old_string not found in {$filePath}.";

        if (count($suggestions) > 0) {
            $limited = array_slice($suggestions, 0, 5);
            $message .= "\n\nDid you mean one of these?\n".implode("\n", $limited);
        }

        return $message;
    }

    protected function findLineNumbers(string $content, string $needle): array
    {
        $lines = [];
        $offset = 0;

        while (($pos = strpos($content, $needle, $offset)) !== false) {
            $lines[] = substr_count($content, "\n", 0, $pos) + 1;
            $offset = $pos + strlen($needle);
        }

        return $lines;
    }

    protected function replaceFirst(string $content, string $old, string $new): string
    {
        $pos = strpos($content, $old);

        if ($pos === false) {
            return $content;
        }

        return substr($content, 0, $pos).$new.substr($content, $pos + strlen($old));
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

        $checkPath = realpath($filePath);

        if ($checkPath === false || ! str_starts_with($checkPath, $realBase)) {
            return "Path is not allowed. Must be within: {$this->basePath}";
        }

        return null;
    }
}
