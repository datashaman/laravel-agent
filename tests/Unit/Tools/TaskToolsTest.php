<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Support\InMemoryTaskStore;
use DataShaman\LaravelAgent\Tools\Tasks\TaskCreate;
use DataShaman\LaravelAgent\Tools\Tasks\TaskGet;
use DataShaman\LaravelAgent\Tools\Tasks\TaskList;
use DataShaman\LaravelAgent\Tools\Tasks\TaskOutput;
use DataShaman\LaravelAgent\Tools\Tasks\TaskStop;
use DataShaman\LaravelAgent\Tools\Tasks\TaskUpdate;
use DataShaman\LaravelAgent\Tools\Tasks\TodoWrite;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

class TaskToolsTest extends TestCase
{
    private InMemoryTaskStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new InMemoryTaskStore;
    }

    public function test_task_create(): void
    {
        $tool = new TaskCreate($this->store);
        $result = (string) $tool->handle(new Request(['title' => 'My task']));

        $this->assertStringContainsString('Task created', $result);
        $this->assertStringContainsString('My task', $result);
        $this->assertCount(1, $this->store->list());
    }

    public function test_task_get(): void
    {
        $task = $this->store->create('Test task', 'A description');
        $tool = new TaskGet($this->store);

        $result = (string) $tool->handle(new Request(['id' => $task->id]));

        $this->assertStringContainsString('Test task', $result);
        $this->assertStringContainsString('A description', $result);
    }

    public function test_task_get_not_found(): void
    {
        $tool = new TaskGet($this->store);
        $result = (string) $tool->handle(new Request(['id' => 'nope']));

        $this->assertStringContainsString('not found', $result);
    }

    public function test_task_list(): void
    {
        $this->store->create('Task 1');
        $this->store->create('Task 2');
        $tool = new TaskList($this->store);

        $result = (string) $tool->handle(new Request([]));

        $this->assertStringContainsString('Task 1', $result);
        $this->assertStringContainsString('Task 2', $result);
    }

    public function test_task_list_filters_by_status(): void
    {
        $this->store->create('Task 1', '', 'pending');
        $this->store->create('Task 2', '', 'completed');
        $tool = new TaskList($this->store);

        $result = (string) $tool->handle(new Request(['status' => 'completed']));

        $this->assertStringContainsString('Task 2', $result);
        $this->assertStringNotContainsString('Task 1', $result);
    }

    public function test_task_list_empty(): void
    {
        $tool = new TaskList($this->store);
        $result = (string) $tool->handle(new Request([]));

        $this->assertStringContainsString('No tasks found', $result);
    }

    public function test_task_output(): void
    {
        $task = $this->store->create('Task');
        $this->store->update($task->id, ['output' => 'hello world', 'status' => 'completed', 'exit_code' => 0]);
        $tool = new TaskOutput($this->store);

        $result = (string) $tool->handle(new Request(['id' => $task->id]));

        $this->assertStringContainsString('hello world', $result);
        $this->assertStringContainsString('COMPLETED', $result);
    }

    public function test_task_stop_non_running(): void
    {
        $task = $this->store->create('Task');
        $tool = new TaskStop($this->store);

        $result = (string) $tool->handle(new Request(['id' => $task->id]));

        $this->assertStringContainsString('not currently running', $result);
    }

    public function test_task_update(): void
    {
        $task = $this->store->create('Task');
        $tool = new TaskUpdate($this->store);

        $result = (string) $tool->handle(new Request([
            'id' => $task->id,
            'status' => 'completed',
            'title' => 'Updated task',
        ]));

        $this->assertStringContainsString('Task updated', $result);
        $this->assertStringContainsString('Updated task', $result);
    }

    public function test_task_update_delete(): void
    {
        $task = $this->store->create('Task');
        $tool = new TaskUpdate($this->store);

        $result = (string) $tool->handle(new Request([
            'id' => $task->id,
            'delete' => true,
        ]));

        $this->assertStringContainsString('deleted', $result);
        $this->assertNull($this->store->get($task->id));
    }

    public function test_todo_write_creates_and_reconciles(): void
    {
        $this->store->create('Old task', '', 'pending');
        $tool = new TodoWrite($this->store);

        $result = (string) $tool->handle(new Request([
            'tasks' => [
                ['title' => 'New task 1', 'status' => 'pending'],
                ['title' => 'New task 2', 'status' => 'in_progress'],
            ],
        ]));

        $this->assertStringContainsString('2 tasks', $result);
        $this->assertStringContainsString('2 created', $result);
        $this->assertStringContainsString('1 removed', $result);

        $tasks = $this->store->list();
        $this->assertCount(2, $tasks);
    }
}
