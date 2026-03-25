<?php

namespace DataShaman\LaravelAgent\Tools\Tasks;

use DataShaman\LaravelAgent\Support\ProcessManager;
use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskStop implements Tool
{
    public function __construct(
        protected TaskStore $store,
        protected ?ProcessManager $processManager = null,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? 'Kills a running background task by its ID.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The task ID to stop')
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

        if (! in_array($task->status, ['running', 'queued'], true)) {
            return "Task is not currently running (status: {$task->status}).";
        }

        if ($this->processManager !== null) {
            $this->processManager->stop($id);
        } else {
            $this->store->stop($id);
        }

        return "Task stopped: {$id}";
    }
}
