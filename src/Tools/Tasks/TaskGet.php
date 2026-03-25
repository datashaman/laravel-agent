<?php

namespace DataShaman\LaravelAgent\Tools\Tasks;

use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskGet implements Tool
{
    public function __construct(
        protected TaskStore $store,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? 'Retrieves full details for a specific task by its ID.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The task ID to retrieve')
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $id = (string) $request->string('id');
        $task = $this->store->get($id);

        if ($task === null) {
            return "Task not found: {$id}";
        }

        $output = "ID: {$task->id}\nTitle: {$task->title}\nStatus: {$task->status}";

        if ($task->description !== '') {
            $output .= "\nDescription: {$task->description}";
        }

        if ($task->output !== '') {
            $output .= "\nOutput: {$task->output}";
        }

        if ($task->exitCode !== null) {
            $output .= "\nExit Code: {$task->exitCode}";
        }

        return $output;
    }
}
