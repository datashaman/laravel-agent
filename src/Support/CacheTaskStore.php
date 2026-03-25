<?php

namespace DataShaman\LaravelAgent\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class CacheTaskStore implements TaskStore
{
    public function __construct(
        protected CacheRepository $cache,
        protected string $prefix = 'laravel-agent:tasks:',
        protected int $ttl = 3600,
    ) {}

    public function create(string $title, string $description = '', string $status = 'pending'): Task
    {
        $task = new Task(
            id: Str::uuid()->toString(),
            title: $title,
            description: $description,
            status: $status,
        );

        $this->save($task);
        $this->addToIndex($task->id);

        return $task;
    }

    public function get(string $id): ?Task
    {
        $data = $this->cache->get($this->prefix.$id);

        return $data ? Task::fromArray($data) : null;
    }

    public function list(?string $status = null): array
    {
        $ids = $this->cache->get($this->prefix.'index', []);
        $tasks = [];

        foreach ($ids as $id) {
            $task = $this->get($id);
            if ($task === null) {
                continue;
            }
            if ($status !== null && $task->status !== $status) {
                continue;
            }
            $tasks[] = $task;
        }

        return $tasks;
    }

    public function update(string $id, array $data): ?Task
    {
        $task = $this->get($id);

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
        $this->save($task);

        return $task;
    }

    public function output(string $id): ?string
    {
        return $this->get($id)?->output;
    }

    public function stop(string $id): bool
    {
        $task = $this->get($id);

        if ($task === null) {
            return false;
        }

        if ($task->processId !== null) {
            posix_kill($task->processId, SIGTERM);
        }

        $task->status = 'stopped';
        $task->updatedAt = microtime(true);
        $this->save($task);

        return true;
    }

    public function delete(string $id): bool
    {
        if ($this->get($id) === null) {
            return false;
        }

        $this->cache->forget($this->prefix.$id);
        $this->removeFromIndex($id);

        return true;
    }

    protected function save(Task $task): void
    {
        $this->cache->put($this->prefix.$task->id, $task->toArray(), $this->ttl);
    }

    protected function addToIndex(string $id): void
    {
        $ids = $this->cache->get($this->prefix.'index', []);
        $ids[] = $id;
        $this->cache->put($this->prefix.'index', array_unique($ids), $this->ttl);
    }

    protected function removeFromIndex(string $id): void
    {
        $ids = $this->cache->get($this->prefix.'index', []);
        $ids = array_values(array_filter($ids, fn ($i) => $i !== $id));
        $this->cache->put($this->prefix.'index', $ids, $this->ttl);
    }
}
