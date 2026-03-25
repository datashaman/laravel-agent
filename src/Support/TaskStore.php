<?php

namespace DataShaman\LaravelAgent\Support;

interface TaskStore
{
    public function create(string $title, string $description = '', string $status = 'pending'): Task;

    public function get(string $id): ?Task;

    /**
     * @return Task[]
     */
    public function list(?string $status = null): array;

    public function update(string $id, array $data): ?Task;

    public function output(string $id): ?string;

    public function stop(string $id): bool;

    public function delete(string $id): bool;
}
