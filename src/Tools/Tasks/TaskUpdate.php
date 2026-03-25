<?php

namespace DataShaman\LaravelAgent\Tools\Tasks;

use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskUpdate implements Tool
{
    public function __construct(
        protected TaskStore $store,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? 'Updates task status, title, description, or deletes a task.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The task ID to update')
                ->required(),
            'status' => $schema->string()
                ->description('New status')
                ->enum(['pending', 'in_progress', 'completed', 'failed']),
            'title' => $schema->string()
                ->description('New title'),
            'description' => $schema->string()
                ->description('New description'),
            'delete' => $schema->boolean()
                ->description('Set to true to delete the task'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $id = (string) $request->string('id');

        if ($request->boolean('delete')) {
            $deleted = $this->store->delete($id);

            return $deleted
                ? "Task deleted: {$id}"
                : "Task not found: {$id}";
        }

        $updates = [];

        foreach (['status', 'title', 'description'] as $field) {
            $value = $request[$field] ?? null;

            if ($value !== null) {
                $updates[$field] = $value;
            }
        }

        if (empty($updates)) {
            return 'No updates provided.';
        }

        $task = $this->store->update($id, $updates);

        if ($task === null) {
            return "Task not found: {$id}";
        }

        return "Task updated.\nID: {$task->id}\nTitle: {$task->title}\nStatus: {$task->status}";
    }
}
