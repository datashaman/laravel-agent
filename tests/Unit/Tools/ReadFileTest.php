<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Tools\Filesystem\ReadFile;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

class ReadFileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/laravel-agent-test-'.uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_reads_file_with_line_numbers(): void
    {
        file_put_contents($this->tmpDir.'/test.txt', "line one\nline two\nline three\n");
        $tool = new ReadFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request(['file_path' => $this->tmpDir.'/test.txt']));

        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('line one', $result);
        $this->assertStringContainsString('line three', $result);
    }

    public function test_reads_with_offset_and_limit(): void
    {
        $content = implode("\n", array_map(fn ($i) => "line {$i}", range(1, 50)));
        file_put_contents($this->tmpDir.'/big.txt', $content);
        $tool = new ReadFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $this->tmpDir.'/big.txt',
            'offset' => 10,
            'limit' => 5,
        ]));

        $this->assertStringContainsString('line 10', $result);
        $this->assertStringContainsString('line 14', $result);
        $this->assertStringNotContainsString('line 1'."\t", $result);
    }

    public function test_rejects_path_outside_basepath(): void
    {
        $tool = new ReadFile(basePath: $this->tmpDir);
        $result = (string) $tool->handle(new Request(['file_path' => '/etc/passwd']));

        $this->assertStringContainsString('not allowed', $result);
    }

    public function test_handles_nonexistent_file(): void
    {
        $tool = new ReadFile(basePath: $this->tmpDir);
        $result = (string) $tool->handle(new Request(['file_path' => $this->tmpDir.'/nope.txt']));

        $this->assertStringContainsString('does not exist', $result);
    }

    public function test_detects_binary_files(): void
    {
        file_put_contents($this->tmpDir.'/binary.bin', "hello\x00world");
        $tool = new ReadFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request(['file_path' => $this->tmpDir.'/binary.bin']));

        $this->assertStringContainsString('Binary file', $result);
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
