<?php

namespace DataShaman\LaravelAgent\Support;

use Illuminate\Support\Str;

class InMemoryTaskStore implements TaskStore
{
    /** @var array<string, Task> */
    protected array $tasks = [];

    public function create(string $title, string $description = '', string $status = 'pending'): Task
    {
        $task = new Task(
            id: Str::uuid()->toString(),
            title: $title,
            description: $description,
            status: $status,
        );

        $this->tasks[$task->id] = $task;

        return $task;
    }

    public function get(string $id): ?Task
    {
        return $this->tasks[$id] ?? null;
    }

    public function list(?string $status = null): array
    {
        $tasks = array_values($this->tasks);

        if ($status !== null) {
            $tasks = array_filter($tasks, fn (Task $t) => $t->status === $status);
            $tasks = array_values($tasks);
        }

        return $tasks;
    }

    public function update(string $id, array $data): ?Task
    {
        $task = $this->tasks[$id] ?? null;

        if ($task === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            $property = match ($key) {
                'title' => 'title',
                'description' => 'description',
                'status' => 'status',
                'output' => 'output',
                'exit_code' => 'exitCode',
                'process_id' => 'processId',
                'dependencies' => 'dependencies',
                default => null,
            };

            if ($property !== null) {
                $task->{$property} = $value;
            }
        }

        $task->updatedAt = microtime(true);

        return $task;
    }

    public function output(string $id): ?string
    {
        $task = $this->tasks[$id] ?? null;

        return $task?->output;
    }

    public function stop(string $id): bool
    {
        $task = $this->tasks[$id] ?? null;

        if ($task === null) {
            return false;
        }

        if ($task->processId !== null) {
            posix_kill($task->processId, SIGTERM);
        }

        $task->status = 'stopped';
        $task->updatedAt = microtime(true);

        return true;
    }

    public function delete(string $id): bool
    {
        if (! isset($this->tasks[$id])) {
            return false;
        }

        unset($this->tasks[$id]);

        return true;
    }
}
