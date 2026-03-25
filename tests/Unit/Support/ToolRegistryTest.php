<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Support;

use DataShaman\LaravelAgent\Support\ToolRegistry;
use DataShaman\LaravelAgent\Tools\Filesystem\ReadFile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;
use Stringable;

class ToolRegistryTest extends TestCase
{
    public function test_register_and_get(): void
    {
        $tool = new ReadFile;
        $registry = new ToolRegistry([$tool]);

        $retrieved = $registry->get('ReadFile');

        $this->assertNotNull($retrieved);
    }

    public function test_search_by_keyword(): void
    {
        $tool1 = $this->createTool('Read files from disk');
        $tool2 = $this->createTool('Search files by pattern');

        $registry = new ToolRegistry([$tool1, $tool2]);

        $results = $registry->search('files');

        $this->assertCount(2, $results);
    }

    public function test_search_returns_empty_for_no_match(): void
    {
        $tool = $this->createTool('Read files');
        $registry = new ToolRegistry([$tool]);

        $results = $registry->search('database');

        $this->assertEmpty($results);
    }

    public function test_search_respects_max_results(): void
    {
        $tools = [];
        for ($i = 0; $i < 10; $i++) {
            $tools[] = $this->createTool("Tool {$i} for file operations");
        }

        $registry = new ToolRegistry($tools);

        $results = $registry->search('file', 3);

        $this->assertCount(3, $results);
    }

    public function test_all_returns_all_tools(): void
    {
        $tool1 = $this->createTool('Tool A');
        $tool2 = $this->createTool('Tool B');

        $registry = new ToolRegistry([$tool1, $tool2]);

        $this->assertCount(2, $registry->all());
    }

    protected function createTool(string $desc): Tool
    {
        return new class($desc) implements Tool {
            private static int $counter = 0;

            private string $className;

            public function __construct(private string $desc)
            {
                $this->className = 'MockTool'.self::$counter++;
            }

            public function description(): Stringable|string
            {
                return $this->desc;
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function handle(Request $request): Stringable|string
            {
                return '';
            }
        };
    }
}
