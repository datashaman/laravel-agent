<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Support;

use DataShaman\LaravelAgent\Support\InMemoryTaskStore;
use PHPUnit\Framework\TestCase;

class InMemoryTaskStoreTest extends TestCase
{
    private InMemoryTaskStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new InMemoryTaskStore;
    }

    public function test_create_returns_task_with_id(): void
    {
        $task = $this->store->create('Test task');

        $this->assertNotEmpty($task->id);
        $this->assertEquals('Test task', $task->title);
        $this->assertEquals('pending', $task->status);
    }

    public function test_create_with_description_and_status(): void
    {
        $task = $this->store->create('Task', 'A description', 'in_progress');

        $this->assertEquals('A description', $task->description);
        $this->assertEquals('in_progress', $task->status);
    }

    public function test_get_returns_task(): void
    {
        $created = $this->store->create('Test');
        $retrieved = $this->store->get($created->id);

        $this->assertNotNull($retrieved);
        $this->assertEquals($created->id, $retrieved->id);
    }

    public function test_get_returns_null_for_missing(): void
    {
        $this->assertNull($this->store->get('nonexistent'));
    }

    public function test_list_returns_all_tasks(): void
    {
        $this->store->create('Task 1');
        $this->store->create('Task 2');
        $this->store->create('Task 3');

        $tasks = $this->store->list();

        $this->assertCount(3, $tasks);
    }

    public function test_list_filters_by_status(): void
    {
        $this->store->create('Task 1', '', 'pending');
        $this->store->create('Task 2', '', 'in_progress');
        $this->store->create('Task 3', '', 'pending');

        $pending = $this->store->list('pending');
        $this->assertCount(2, $pending);

        $inProgress = $this->store->list('in_progress');
        $this->assertCount(1, $inProgress);
    }

    public function test_update_changes_properties(): void
    {
        $task = $this->store->create('Original');

        $updated = $this->store->update($task->id, [
            'title' => 'Updated',
            'status' => 'completed',
        ]);

        $this->assertNotNull($updated);
        $this->assertEquals('Updated', $updated->title);
        $this->assertEquals('completed', $updated->status);
    }

    public function test_update_returns_null_for_missing(): void
    {
        $this->assertNull($this->store->update('nonexistent', ['title' => 'x']));
    }

    public function test_output_returns_task_output(): void
    {
        $task = $this->store->create('Task');
        $this->store->update($task->id, ['output' => 'some output']);

        $this->assertEquals('some output', $this->store->output($task->id));
    }

    public function test_output_returns_null_for_missing(): void
    {
        $this->assertNull($this->store->output('nonexistent'));
    }

    public function test_delete_removes_task(): void
    {
        $task = $this->store->create('Task');

        $this->assertTrue($this->store->delete($task->id));
        $this->assertNull($this->store->get($task->id));
    }

    public function test_delete_returns_false_for_missing(): void
    {
        $this->assertFalse($this->store->delete('nonexistent'));
    }
}
