<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Tools\Filesystem\WriteFile;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

class WriteFileTest extends TestCase
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

    public function test_creates_new_file(): void
    {
        $tool = new WriteFile(basePath: $this->tmpDir);
        $filePath = $this->tmpDir.'/new.txt';

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'content' => "hello\nworld\n",
        ]));

        $this->assertStringContainsString('Created', $result);
        $this->assertFileExists($filePath);
        $this->assertEquals("hello\nworld\n", file_get_contents($filePath));
    }

    public function test_overwrites_existing_file(): void
    {
        $filePath = $this->tmpDir.'/existing.txt';
        file_put_contents($filePath, 'old content');
        $tool = new WriteFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'content' => 'new content',
        ]));

        $this->assertStringContainsString('Updated', $result);
        $this->assertEquals('new content', file_get_contents($filePath));
    }

    public function test_creates_parent_directories(): void
    {
        $tool = new WriteFile(basePath: $this->tmpDir);
        $filePath = $this->tmpDir.'/deep/nested/dir/file.txt';

        $tool->handle(new Request([
            'file_path' => $filePath,
            'content' => 'content',
        ]));

        $this->assertFileExists($filePath);
    }

    public function test_rejects_path_outside_basepath(): void
    {
        $tool = new WriteFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => '/tmp/outside.txt',
            'content' => 'hack',
        ]));

        $this->assertStringContainsString('not allowed', $result);
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
