<?php

namespace DataShaman\LaravelAgent\Tools\Tasks;

use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskList implements Tool
{
    public function __construct(
        protected TaskStore $store,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? 'Lists all tasks with their current status. Supports filtering by status.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->description('Optional status filter')
                ->enum(['pending', 'in_progress', 'running', 'completed', 'failed', 'stopped', 'queued']),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $status = $request['status'] ?? null;
        $tasks = $this->store->list($status);

        if (empty($tasks)) {
            $filter = $status ? " with status '{$status}'" : '';

            return "No tasks found{$filter}.";
        }

        $output = '';

        foreach ($tasks as $task) {
            $output .= "[{$task->status}] {$task->id}: {$task->title}\n";
        }

        return rtrim($output);
    }
}
