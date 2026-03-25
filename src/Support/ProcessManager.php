<?php

namespace DataShaman\LaravelAgent\Support;

use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;

class ProcessManager
{
    /** @var array<string, InvokedProcess> */
    protected array $processes = [];

    public function __construct(
        protected TaskStore $store,
    ) {}

    public function start(string $command, string $basePath, int $timeout = 120, ?int $idleTimeout = null): Task
    {
        $process = Process::path($basePath)
            ->timeout($timeout)
            ->when($idleTimeout !== null, fn ($p) => $p->idleTimeout($idleTimeout))
            ->start($command);

        $task = $this->store->create(
            title: $command,
            description: "Background command: {$command}",
            status: 'running',
        );

        $this->store->update($task->id, [
            'process_id' => $process->id(),
        ]);

        $this->processes[$task->id] = $process;

        return $task;
    }

    public function output(string $taskId): ?string
    {
        $process = $this->processes[$taskId] ?? null;

        if ($process !== null) {
            $this->syncProcessState($taskId, $process);

            return $process->output().$process->errorOutput();
        }

        return $this->store->output($taskId);
    }

    public function isRunning(string $taskId): bool
    {
        $process = $this->processes[$taskId] ?? null;

        if ($process !== null) {
            return $process->running();
        }

        $task = $this->store->get($taskId);

        return $task !== null && $task->status === 'running';
    }

    public function stop(string $taskId): bool
    {
        $process = $this->processes[$taskId] ?? null;

        if ($process !== null) {
            $process->kill();
            unset($this->processes[$taskId]);

            $this->store->update($taskId, [
                'status' => 'stopped',
                'output' => $process->output().$process->errorOutput(),
            ]);

            return true;
        }

        return $this->store->stop($taskId);
    }

    protected function syncProcessState(string $taskId, InvokedProcess $process): void
    {
        if (! $process->running()) {
            $result = $process->wait();

            $this->store->update($taskId, [
                'status' => $result->exitCode() === 0 ? 'completed' : 'failed',
                'output' => $result->output().$result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ]);

            unset($this->processes[$taskId]);
        }
    }
}
