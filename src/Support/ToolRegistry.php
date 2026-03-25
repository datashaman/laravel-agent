<?php

namespace DataShaman\LaravelAgent\Support;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;

class ToolRegistry
{
    /** @var Tool[] */
    protected array $tools = [];

    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(Tool $tool): void
    {
        $this->tools[$this->toolName($tool)] = $tool;
    }

    /**
     * @return array<string, array{name: string, description: string, schema: array}>
     */
    public function search(string $query, int $maxResults = 5): array
    {
        $query = mb_strtolower($query);
        $keywords = preg_split('/[\s,]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $scored = [];

        foreach ($this->tools as $name => $tool) {
            $searchable = mb_strtolower($name.' '.$tool->description());
            $score = 0;

            foreach ($keywords as $keyword) {
                if (str_contains($searchable, $keyword)) {
                    $score++;
                }
            }

            if ($score > 0) {
                $scored[$name] = $score;
            }
        }

        arsort($scored);
        $results = [];

        foreach (array_slice($scored, 0, $maxResults, true) as $name => $score) {
            $tool = $this->tools[$name];
            $schema = new JsonSchemaTypeFactory;

            $results[$name] = [
                'name' => $name,
                'description' => (string) $tool->description(),
                'schema' => $tool->schema($schema),
            ];
        }

        return $results;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return Tool[]
     */
    public function all(): array
    {
        return $this->tools;
    }

    protected function toolName(Tool $tool): string
    {
        $class = get_class($tool);

        // Anonymous classes contain NUL bytes or @ in their name
        if (str_contains($class, '@') || str_contains($class, "\0")) {
            return $class.'#'.spl_object_id($tool);
        }

        $parts = explode('\\', $class);

        return end($parts);
    }
}
