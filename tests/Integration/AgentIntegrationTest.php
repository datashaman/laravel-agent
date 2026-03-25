<?php

namespace DataShaman\LaravelAgent\Tests\Integration;

use DataShaman\LaravelAgent\Support\InMemoryTaskStore;
use DataShaman\LaravelAgent\Support\ToolRegistry;
use DataShaman\LaravelAgent\Tests\TestCase;
use DataShaman\LaravelAgent\Tools\Execution\RunCommand;
use DataShaman\LaravelAgent\Tools\Filesystem\EditFile;
use DataShaman\LaravelAgent\Tools\Filesystem\GlobFiles;
use DataShaman\LaravelAgent\Tools\Filesystem\GrepFiles;
use DataShaman\LaravelAgent\Tools\Filesystem\ReadFile;
use DataShaman\LaravelAgent\Tools\Filesystem\WriteFile;
use DataShaman\LaravelAgent\Tools\Meta\ToolSearch;
use DataShaman\LaravelAgent\Tools\Tasks\TaskCreate;
use DataShaman\LaravelAgent\Tools\Tasks\TaskGet;
use DataShaman\LaravelAgent\Tools\Tasks\TaskList;
use DataShaman\LaravelAgent\Tools\Tasks\TaskUpdate;
use DataShaman\LaravelAgent\Tools\Tasks\TodoWrite;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AgentIntegrationTest extends TestCase
{
    public function test_all_tools_implement_tool_interface(): void
    {
        $tmpDir = sys_get_temp_dir().'/laravel-agent-integration-'.uniqid();
        mkdir($tmpDir, 0755, true);

        $store = new InMemoryTaskStore;
        $registry = new ToolRegistry;

        $tools = [
            new ReadFile(basePath: $tmpDir),
            new WriteFile(basePath: $tmpDir),
            new EditFile(basePath: $tmpDir),
            new GlobFiles(basePath: $tmpDir),
            new GrepFiles(basePath: $tmpDir),
            new RunCommand(basePath: $tmpDir),
            new TaskCreate($store),
            new TaskGet($store),
            new TaskList($store),
            new TaskUpdate($store),
            new TodoWrite($store),
            new ToolSearch($registry),
        ];

        foreach ($tools as $tool) {
            $this->assertInstanceOf(Tool::class, $tool);
            $this->assertNotEmpty((string) $tool->description());
        }

        $this->rmdir($tmpDir);
    }

    public function test_tools_work_in_has_tools_context(): void
    {
        $tmpDir = sys_get_temp_dir().'/laravel-agent-integration-'.uniqid();
        mkdir($tmpDir, 0755, true);

        $store = new InMemoryTaskStore;
        $agent = new TestAgent($tmpDir, $store);

        $tools = $agent->tools();
        $this->assertNotEmpty($tools);

        foreach ($tools as $tool) {
            $this->assertInstanceOf(Tool::class, $tool);
        }

        $this->rmdir($tmpDir);
    }

    public function test_write_read_edit_roundtrip(): void
    {
        $tmpDir = sys_get_temp_dir().'/laravel-agent-integration-'.uniqid();
        mkdir($tmpDir, 0755, true);

        $write = new WriteFile(basePath: $tmpDir);
        $read = new ReadFile(basePath: $tmpDir);
        $edit = new EditFile(basePath: $tmpDir);

        // Write
        $writeResult = (string) $write->handle(new Request([
            'file_path' => $tmpDir.'/test.txt',
            'content' => "hello world\n",
        ]));
        $this->assertStringContainsString('Created', $writeResult);

        // Read
        $readResult = (string) $read->handle(new Request([
            'file_path' => $tmpDir.'/test.txt',
        ]));
        $this->assertStringContainsString('hello world', $readResult);

        // Edit
        $editResult = (string) $edit->handle(new Request([
            'file_path' => $tmpDir.'/test.txt',
            'old_string' => 'hello world',
            'new_string' => 'hello universe',
        ]));
        $this->assertStringContainsString('Successfully edited', $editResult);

        // Verify
        $this->assertEquals("hello universe\n", file_get_contents($tmpDir.'/test.txt'));

        $this->rmdir($tmpDir);
    }

    private function rmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->rmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

class TestAgent implements HasTools
{
    public function __construct(
        private string $basePath,
        private InMemoryTaskStore $store,
    ) {}

    public function tools(): iterable
    {
        return [
            new ReadFile(basePath: $this->basePath),
            new WriteFile(basePath: $this->basePath),
            new EditFile(basePath: $this->basePath),
            new GlobFiles(basePath: $this->basePath),
            new GrepFiles(basePath: $this->basePath),
            new RunCommand(basePath: $this->basePath),
            new TaskCreate($this->store),
            new TaskGet($this->store),
            new TaskList($this->store),
            new TaskUpdate($this->store),
            new TodoWrite($this->store),
        ];
    }
}
