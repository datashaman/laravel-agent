<?php

namespace DataShaman\LaravelAgent\Tools\Meta;

use DataShaman\LaravelAgent\Support\ToolRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ToolSearch implements Tool
{
    public function __construct(
        protected ToolRegistry $registry,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Searches for and loads deferred tools by keyword.

        - Use this when you need a tool that isn't currently available.
        - Search by keyword (e.g. "edit file", "grep", "task") to discover available tools.
        - Returns matching tool names, descriptions, and parameter schemas so they can be used.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Keywords to search for tools (e.g. "edit file", "grep", "run command")')
                ->required(),
            'max_results' => $schema->integer()
                ->description('Maximum number of results to return. Default: 5'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $query = (string) $request->string('query');
        $maxResults = $request['max_results'] ?? 5;

        $results = $this->registry->search($query, $maxResults);

        if (empty($results)) {
            return "No tools matched query: \"{$query}\". Try different keywords.";
        }

        $output = 'Found '.count($results)." tool(s):\n\n";

        foreach ($results as $name => $info) {
            $output .= "## {$name}\n";
            $output .= "{$info['description']}\n";
            $output .= "Parameters:\n";

            foreach ($info['schema'] as $paramName => $paramDef) {
                $desc = '';
                $paramArray = method_exists($paramDef, 'toArray') ? $paramDef->toArray() : [];
                $desc = $paramArray['description'] ?? '';
                $required = ($paramArray['required'] ?? false) ? ' (required)' : '';
                $output .= "  - {$paramName}{$required}: {$desc}\n";
            }

            $output .= "\n";
        }

        return rtrim($output);
    }
}
