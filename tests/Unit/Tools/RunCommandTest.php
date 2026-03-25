<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Support\InMemoryTaskStore;
use DataShaman\LaravelAgent\Support\ProcessManager;
use DataShaman\LaravelAgent\Tests\TestCase;
use DataShaman\LaravelAgent\Tools\Execution\RunCommand;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Tools\Request;

class RunCommandTest extends TestCase
{
    public function test_sync_execution(): void
    {
        Process::fake([
            'echo hello' => Process::result(output: 'hello'),
        ]);

        $tool = new RunCommand;

        $result = (string) $tool->handle(new Request(['command' => 'echo hello']));

        $this->assertStringContainsString('hello', $result);
    }

    public function test_sync_execution_with_error(): void
    {
        Process::fake([
            'failing-command' => Process::result(
                output: '',
                errorOutput: 'something went wrong',
                exitCode: 1,
            ),
        ]);

        $tool = new RunCommand;

        $result = (string) $tool->handle(new Request(['command' => 'failing-command']));

        $this->assertStringContainsString('something went wrong', $result);
        $this->assertStringContainsString('Exit code: 1', $result);
    }

    public function test_deny_list_blocks_command(): void
    {
        $tool = new RunCommand(deny: ['rm -rf *', 'sudo *']);

        $result = (string) $tool->handle(new Request(['command' => 'rm -rf /']));

        $this->assertStringContainsString('not permitted', $result);
    }

    public function test_allow_list_restricts_commands(): void
    {
        $tool = new RunCommand(allow: ['git *', 'composer *']);

        $result = (string) $tool->handle(new Request(['command' => 'curl https://example.com']));

        $this->assertStringContainsString('not permitted', $result);
    }

    public function test_deny_takes_precedence_over_allow(): void
    {
        $tool = new RunCommand(
            allow: ['git *'],
            deny: ['git push --force *'],
        );

        $result = (string) $tool->handle(new Request(['command' => 'git push --force origin main']));

        $this->assertStringContainsString('not permitted', $result);
    }

    public function test_background_async_returns_task_id(): void
    {
        Process::fake([
            'long-running' => Process::result(output: 'done'),
        ]);

        $store = new InMemoryTaskStore;
        $processManager = new ProcessManager($store);
        $tool = new RunCommand(
            executor: 'async',
            taskStore: $store,
            processManager: $processManager,
        );

        $result = (string) $tool->handle(new Request([
            'command' => 'long-running',
            'background' => true,
        ]));

        $this->assertStringContainsString('Task ID:', $result);
    }

    public function test_queued_returns_task_id(): void
    {
        $store = new InMemoryTaskStore;
        $tool = new RunCommand(
            executor: 'queued',
            taskStore: $store,
        );

        $result = (string) $tool->handle(new Request([
            'command' => 'some-command',
            'background' => true,
        ]));

        $this->assertStringContainsString('Task ID:', $result);
        $this->assertStringContainsString('queued', strtolower($result));
    }
}
