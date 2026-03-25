<?php

namespace DataShaman\LaravelAgent\Tools\Tasks;

use DataShaman\LaravelAgent\Support\ProcessManager;
use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TaskOutput implements Tool
{
    public function __construct(
        protected TaskStore $store,
        protected ?ProcessManager $processManager = null,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Retrieves output from a background task.

        - Returns the current output (stdout + stderr) of the associated process.
        - Indicates whether the process is still running or has completed.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The task ID to get output from')
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

        $output = '';
        $running = false;

        if ($this->processManager !== null) {
            $output = $this->processManager->output($id) ?? '';
            $running = $this->processManager->isRunning($id);
        } else {
            $output = $task->output;
            $running = in_array($task->status, ['running', 'queued'], true);
        }

        $status = $running ? 'RUNNING' : strtoupper($task->status);
        $response = "Status: {$status}\n";

        if ($output !== '') {
            $response .= "Output:\n{$output}";
        } else {
            $response .= 'No output yet.';
        }

        if (! $running && $task->exitCode !== null) {
            $response .= "\nExit Code: {$task->exitCode}";
        }

        return $response;
    }
}
