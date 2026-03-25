<?php

namespace DataShaman\LaravelAgent\Tools\Execution;

use DataShaman\LaravelAgent\Jobs\RunCommandJob;
use DataShaman\LaravelAgent\Support\ProcessManager;
use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RunCommand implements Tool
{
    public function __construct(
        protected string $basePath = '',
        protected string $executor = 'sync',
        protected int $timeout = 120,
        protected ?int $idleTimeout = null,
        protected array $allow = [],
        protected array $deny = [],
        protected ?TaskStore $taskStore = null,
        protected ?ProcessManager $processManager = null,
        protected ?string $description = null,
    ) {}

    public function description(): Stringable|string
    {
        return $this->description ?? <<<'DESC'
        Executes a shell command and returns its output.

        - Commands run with a configurable timeout (default 120 seconds).
        - Commands can run in foreground (blocking) or background mode.
        - Background commands return a task ID — use TaskOutput to read results and TaskStop to kill them.
        - Be careful with destructive commands. Prefer safe, reversible operations.
        - Avoid interactive commands that require stdin input.
        DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()
                ->description('The shell command to execute')
                ->required(),
            'background' => $schema->boolean()
                ->description('Run the command in the background. Returns a task ID instead of output. Default: false'),
            'timeout' => $schema->integer()
                ->description('Optional timeout in seconds. Defaults to the configured timeout.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $command = (string) $request->string('command');
        $background = $request->boolean('background');
        $timeout = $request['timeout'] ?? $this->timeout;

        if ($error = $this->validateCommand($command)) {
            return $error;
        }

        if ($background) {
            return $this->runBackground($command, $timeout);
        }

        return $this->runForeground($command, $timeout);
    }

    protected function runForeground(string $command, int $timeout): string
    {
        $process = Process::path($this->basePath ?: getcwd())
            ->timeout($timeout);

        if ($this->idleTimeout !== null) {
            $process = $process->idleTimeout($this->idleTimeout);
        }

        $result = $process->run($command);

        $output = $result->output();
        $errorOutput = $result->errorOutput();
        $exitCode = $result->exitCode();

        $response = '';

        if ($output !== '') {
            $response .= $output;
        }

        if ($errorOutput !== '') {
            if ($response !== '') {
                $response .= "\n";
            }
            $response .= "STDERR:\n".$errorOutput;
        }

        if ($exitCode !== 0) {
            $response .= "\nExit code: {$exitCode}";
        }

        if ($response === '') {
            $response = "(no output)\nExit code: {$exitCode}";
        }

        return $response;
    }

    protected function runBackground(string $command, int $timeout): string
    {
        if ($this->executor === 'queued') {
            return $this->runQueued($command, $timeout);
        }

        if ($this->processManager === null) {
            return 'Background execution requires a ProcessManager. Configure one when instantiating RunCommand.';
        }

        $task = $this->processManager->start(
            $command,
            $this->basePath ?: getcwd(),
            $timeout,
            $this->idleTimeout,
        );

        return "Background task started.\nTask ID: {$task->id}\nUse TaskOutput to check results or TaskStop to kill it.";
    }

    protected function runQueued(string $command, int $timeout): string
    {
        if ($this->taskStore === null) {
            return 'Queued execution requires a TaskStore. Configure one when instantiating RunCommand.';
        }

        $task = $this->taskStore->create(
            title: $command,
            description: "Queued command: {$command}",
            status: 'queued',
        );

        RunCommandJob::dispatch(
            $task->id,
            $command,
            $this->basePath ?: getcwd(),
            $timeout,
            $this->idleTimeout,
        );

        return "Command queued.\nTask ID: {$task->id}\nUse TaskOutput to check results or TaskStop to kill it.";
    }

    protected function validateCommand(string $command): ?string
    {
        // Check deny list first (deny takes precedence)
        foreach ($this->deny as $pattern) {
            if (fnmatch($pattern, $command)) {
                return "Command not permitted (matches deny pattern '{$pattern}'): {$command}";
            }
        }

        // Check allow list if configured
        if (! empty($this->allow)) {
            $allowed = false;

            foreach ($this->allow as $pattern) {
                if (fnmatch($pattern, $command)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                return "Command not permitted (does not match any allow pattern): {$command}";
            }
        }

        return null;
    }
}
