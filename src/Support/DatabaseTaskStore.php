<?php

namespace DataShaman\LaravelAgent\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseTaskStore implements TaskStore
{
    public function __construct(
        protected ?string $connection = null,
        protected string $table = 'agent_tasks',
    ) {}

    public function create(string $title, string $description = '', string $status = 'pending'): Task
    {
        $task = new Task(
            id: Str::uuid()->toString(),
            title: $title,
            description: $description,
            status: $status,
        );

        $this->query()->insert([
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'description' => $task->description,
            'output' => $task->output,
            'exit_code' => $task->exitCode,
            'process_id' => $task->processId,
            'dependencies' => json_encode($task->dependencies),
            'created_at' => $task->createdAt,
            'updated_at' => $task->updatedAt,
        ]);

        return $task;
    }

    public function get(string $id): ?Task
    {
        $row = $this->query()->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        return $this->toTask($row);
    }

    public function list(?string $status = null): array
    {
        $query = $this->query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get()->map(fn ($row) => $this->toTask($row))->all();
    }

    public function update(string $id, array $data): ?Task
    {
        $row = $this->query()->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        $updates = [];
        $allowed = ['title', 'description', 'status', 'output', 'exit_code', 'process_id'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $updates[$key] = $value;
            }
            if ($key === 'dependencies') {
                $updates['dependencies'] = json_encode($value);
            }
        }

        $updates['updated_at'] = microtime(true);

        $this->query()->where('id', $id)->update($updates);

        return $this->get($id);
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

        $this->query()->where('id', $id)->update([
            'status' => 'stopped',
            'updated_at' => microtime(true),
        ]);

        return true;
    }

    public function delete(string $id): bool
    {
        return $this->query()->where('id', $id)->delete() > 0;
    }

    protected function query()
    {
        return DB::connection($this->connection)->table($this->table);
    }

    protected function toTask(object $row): Task
    {
        return new Task(
            id: $row->id,
            title: $row->title,
            status: $row->status,
            description: $row->description ?? '',
            output: $row->output ?? '',
            exitCode: $row->exit_code ?? null,
            processId: $row->process_id ?? null,
            dependencies: json_decode($row->dependencies ?? '[]', true),
            createdAt: (float) $row->created_at,
            updatedAt: (float) $row->updated_at,
        );
    }
}
