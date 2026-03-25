<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Tools\Filesystem\GlobFiles;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

class GlobFilesTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/laravel-agent-test-'.uniqid();
        mkdir($this->tmpDir.'/src/Models', 0755, true);
        mkdir($this->tmpDir.'/src/Controllers', 0755, true);

        file_put_contents($this->tmpDir.'/src/Models/User.php', '<?php');
        sleep(1); // ensure different mtime
        file_put_contents($this->tmpDir.'/src/Models/Post.php', '<?php');
        file_put_contents($this->tmpDir.'/src/Controllers/HomeController.php', '<?php');
        file_put_contents($this->tmpDir.'/README.md', '# readme');
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_finds_php_files_recursively(): void
    {
        $tool = new GlobFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request(['pattern' => '**/*.php']));

        $this->assertStringContainsString('User.php', $result);
        $this->assertStringContainsString('Post.php', $result);
        $this->assertStringContainsString('HomeController.php', $result);
        $this->assertStringNotContainsString('README', $result);
    }

    public function test_non_recursive_pattern_only_matches_current_dir(): void
    {
        file_put_contents($this->tmpDir.'/root.php', '<?php');
        $tool = new GlobFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request(['pattern' => '*.php']));

        $this->assertStringContainsString('root.php', $result);
        $this->assertStringNotContainsString('User.php', $result);
    }

    public function test_returns_no_match_message(): void
    {
        $tool = new GlobFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request(['pattern' => '*.xyz']));

        $this->assertStringContainsString('No files matched', $result);
    }

    public function test_rejects_path_outside_basepath(): void
    {
        $tool = new GlobFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => '*.php',
            'path' => '/etc',
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
