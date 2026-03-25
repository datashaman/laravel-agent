<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Tools\Filesystem\GrepFiles;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

class GrepFilesTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/laravel-agent-test-'.uniqid();
        mkdir($this->tmpDir, 0755, true);

        file_put_contents($this->tmpDir.'/app.php', "<?php\nclass App {\n    public function boot() {}\n}\n");
        file_put_contents($this->tmpDir.'/model.php', "<?php\nclass User extends Model {\n    protected \$table = 'users';\n}\n");
        file_put_contents($this->tmpDir.'/readme.md', "# App\nThis is a readme\n");
    }

    protected function tearDown(): void
    {
        $this->rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_files_with_matches_mode(): void
    {
        $tool = new GrepFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => 'class',
            'output_mode' => 'files_with_matches',
        ]));

        $this->assertStringContainsString('app.php', $result);
        $this->assertStringContainsString('model.php', $result);
        $this->assertStringNotContainsString('readme.md', $result);
    }

    public function test_content_mode_with_context(): void
    {
        $tool = new GrepFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => 'boot',
            'output_mode' => 'content',
            'context' => 1,
        ]));

        $this->assertStringContainsString('boot', $result);
        $this->assertStringContainsString('class App', $result);
    }

    public function test_count_mode(): void
    {
        $tool = new GrepFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => 'class',
            'output_mode' => 'count',
        ]));

        $this->assertStringContainsString(':1', $result);
    }

    public function test_glob_filter(): void
    {
        $tool = new GrepFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => 'App',
            'output_mode' => 'files_with_matches',
            'glob' => '*.php',
        ]));

        $this->assertStringContainsString('app.php', $result);
        $this->assertStringNotContainsString('readme.md', $result);
    }

    public function test_case_insensitive(): void
    {
        $tool = new GrepFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => 'CLASS',
            'output_mode' => 'files_with_matches',
            'case_insensitive' => true,
        ]));

        $this->assertStringContainsString('app.php', $result);
    }

    public function test_head_limit(): void
    {
        $tool = new GrepFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => 'class',
            'output_mode' => 'files_with_matches',
            'head_limit' => 1,
        ]));

        $lines = array_filter(explode("\n", trim($result)));
        $this->assertCount(1, $lines);
    }

    public function test_invalid_regex(): void
    {
        $tool = new GrepFiles(basePath: $this->tmpDir);

        $result = (string) $tool->handle(new Request([
            'pattern' => '[invalid',
        ]));

        $this->assertStringContainsString('Invalid regex', $result);
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
