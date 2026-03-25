<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Tools\Filesystem\EditFile;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

class EditFileTest extends TestCase
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

    public function test_replaces_unique_string(): void
    {
        $filePath = $this->tmpDir.'/test.php';
        file_put_contents($filePath, "<?php\nreturn \$user->name;\n");
        $tool = new EditFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'old_string' => 'return $user->name;',
            'new_string' => 'return $user->full_name;',
        ]));

        $this->assertStringContainsString('Successfully edited', $result);
        $this->assertStringContainsString('full_name', file_get_contents($filePath));
    }

    public function test_fails_on_multiple_matches_without_replace_all(): void
    {
        $filePath = $this->tmpDir.'/test.txt';
        file_put_contents($filePath, "foo\nbar\nfoo\nbaz\nfoo\n");
        $tool = new EditFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'old_string' => 'foo',
            'new_string' => 'qux',
        ]));

        $this->assertStringContainsString('3 occurrences', $result);
        $this->assertStringContainsString('replace_all', $result);
    }

    public function test_replace_all_replaces_all_occurrences(): void
    {
        $filePath = $this->tmpDir.'/test.txt';
        file_put_contents($filePath, "foo\nbar\nfoo\n");
        $tool = new EditFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'old_string' => 'foo',
            'new_string' => 'qux',
            'replace_all' => true,
        ]));

        $this->assertStringContainsString('Replaced 2', $result);
        $this->assertEquals("qux\nbar\nqux\n", file_get_contents($filePath));
    }

    public function test_not_found_with_fuzzy_suggestions(): void
    {
        $filePath = $this->tmpDir.'/test.txt';
        file_put_contents($filePath, "return \$user->name;\n");
        $tool = new EditFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'old_string' => 'return $user->Name;',
            'new_string' => 'return $user->full_name;',
        ]));

        $this->assertStringContainsString('not found', $result);
        $this->assertStringContainsString('Did you mean', $result);
    }

    public function test_rejects_identical_old_and_new(): void
    {
        $filePath = $this->tmpDir.'/test.txt';
        file_put_contents($filePath, "hello\n");
        $tool = new EditFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'old_string' => 'hello',
            'new_string' => 'hello',
        ]));

        $this->assertStringContainsString('identical', $result);
    }

    public function test_rejects_empty_old_string(): void
    {
        $filePath = $this->tmpDir.'/test.txt';
        file_put_contents($filePath, "hello\n");
        $tool = new EditFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => $filePath,
            'old_string' => '',
            'new_string' => 'world',
        ]));

        $this->assertStringContainsString('must not be empty', $result);
    }

    public function test_rejects_path_outside_basepath(): void
    {
        $tool = new EditFile(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'file_path' => '/etc/passwd',
            'old_string' => 'root',
            'new_string' => 'hack',
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
