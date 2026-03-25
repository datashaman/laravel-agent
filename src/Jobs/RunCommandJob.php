<?php

namespace DataShaman\LaravelAgent\Jobs;

use DataShaman\LaravelAgent\Support\TaskStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;

class RunCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $taskId,
        public string $command,
        public string $basePath,
        public int $timeout = 120,
        public ?int $idleTimeout = null,
    ) {}

    public function handle(TaskStore $store): void
    {
        $store->update($this->taskId, ['status' => 'running']);

        $process = Process::path($this->basePath)
            ->timeout($this->timeout);

        if ($this->idleTimeout !== null) {
            $process = $process->idleTimeout($this->idleTimeout);
        }

        try {
            $result = $process->run($this->command);

            $store->update($this->taskId, [
                'status' => $result->exitCode() === 0 ? 'completed' : 'failed',
                'output' => $result->output().$result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ]);
        } catch (\Throwable $e) {
            $store->update($this->taskId, [
                'status' => 'failed',
                'output' => "Error: {$e->getMessage()}",
                'exit_code' => 1,
            ]);
        }
    }
}
