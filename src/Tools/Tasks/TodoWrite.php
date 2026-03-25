<?php

namespace DataShaman\LaravelAgent\Tools\Tasks;

use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TodoWrite implements Tool
{
    public function __construct(
        protected TaskStore $store,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Manages the task checklist by setting the full list of tasks at once.

        - Accepts an array of task objects, each with title and status.
        - Reconciles against the existing task list: creates new tasks, updates existing ones, removes tasks not in the list.
        - Useful for batch task management instead of individual TaskCreate/TaskUpdate calls.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tasks' => $schema->array()
                ->description('Array of task objects, each with "title" (required) and "status" (optional, default: pending)')
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $inputTasks = $request['tasks'] ?? [];

        if (empty($inputTasks)) {
            return 'No tasks provided.';
        }

        $existing = $this->store->list();
        $existingByTitle = [];

        foreach ($existing as $task) {
            $existingByTitle[$task->title] = $task;
        }

        $incomingTitles = [];
        $created = 0;
        $updated = 0;

        foreach ($inputTasks as $input) {
            $title = $input['title'] ?? '';

            if ($title === '') {
                continue;
            }

            $status = $input['status'] ?? 'pending';
            $description = $input['description'] ?? '';
            $incomingTitles[] = $title;

            if (isset($existingByTitle[$title])) {
                $existing_task = $existingByTitle[$title];

                if ($existing_task->status !== $status || $existing_task->description !== $description) {
                    $updates = ['status' => $status];

                    if ($description !== '') {
                        $updates['description'] = $description;
                    }

                    $this->store->update($existing_task->id, $updates);
                    $updated++;
                }
            } else {
                $this->store->create($title, $description, $status);
                $created++;
            }
        }

        // Remove tasks not in the incoming list
        $removed = 0;

        foreach ($existing as $task) {
            if (! in_array($task->title, $incomingTitles, true)) {
                $this->store->delete($task->id);
                $removed++;
            }
        }

        $total = count($inputTasks);

        return "Task list updated: {$total} tasks ({$created} created, {$updated} updated, {$removed} removed).";
    }
}
