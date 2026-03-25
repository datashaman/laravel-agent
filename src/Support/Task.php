<?php

namespace DataShaman\LaravelAgent\Support;

class Task
{
    public function __construct(
        public string $id,
        public string $title,
        public string $status = 'pending',
        public string $description = '',
        public string $output = '',
        public ?int $exitCode = null,
        public ?int $processId = null,
        public array $dependencies = [],
        public float $createdAt = 0,
        public float $updatedAt = 0,
    ) {
        if ($this->createdAt === 0.0) {
            $this->createdAt = microtime(true);
        }
        if ($this->updatedAt === 0.0) {
            $this->updatedAt = $this->createdAt;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'description' => $this->description,
            'output' => $this->output,
            'exit_code' => $this->exitCode,
            'process_id' => $this->processId,
            'dependencies' => $this->dependencies,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'],
            title: $data['title'],
            status: $data['status'] ?? 'pending',
            description: $data['description'] ?? '',
            output: $data['output'] ?? '',
            exitCode: $data['exit_code'] ?? null,
            processId: $data['process_id'] ?? null,
            dependencies: $data['dependencies'] ?? [],
            createdAt: $data['created_at'] ?? microtime(true),
            updatedAt: $data['updated_at'] ?? microtime(true),
        );
    }
}
