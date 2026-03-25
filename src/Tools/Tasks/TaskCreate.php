<?php

namespace DataShaman\LaravelAgent\Tools\Tasks;

use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskCreate implements Tool
{
    public function __construct(
        protected TaskStore $store,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Creates a new task in the task list.

        - Use tasks to break down complex work into trackable steps.
        - Tasks can have statuses: pending, in_progress, completed, failed, stopped.
        - Returns the created task with its unique ID.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('Short title describing the task')
                ->required(),
            'description' => $schema->string()
                ->description('Optional longer description of the task'),
            'status' => $schema->string()
                ->description('Initial status. Default: pending')
                ->enum(['pending', 'in_progress', 'completed']),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $title = (string) $request->string('title');
        $description = $request['description'] ?? '';
        $status = $request['status'] ?? 'pending';

        $task = $this->store->create($title, $description, $status);

        return "Task created.\nID: {$task->id}\nTitle: {$task->title}\nStatus: {$task->status}";
    }
}
