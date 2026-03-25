<?php

namespace DataShaman\LaravelAgent\Tests\Unit\Tools;

use DataShaman\LaravelAgent\Support\ToolRegistry;
use DataShaman\LaravelAgent\Tools\Filesystem\EditFile;
use DataShaman\LaravelAgent\Tools\Filesystem\GrepFiles;
use DataShaman\LaravelAgent\Tools\Filesystem\ReadFile;
use DataShaman\LaravelAgent\Tools\Meta\ToolSearch;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;

class ToolSearchTest extends TestCase
{
    public function test_finds_matching_tools(): void
    {
        $registry = new ToolRegistry([
            new ReadFile,
            new EditFile,
            new GrepFiles,
        ]);

        $tool = new ToolSearch($registry);
        $result = (string) $tool->handle(new Request(['query' => 'read file']));

        $this->assertStringContainsString('ReadFile', $result);
    }

    public function test_no_matches(): void
    {
        $registry = new ToolRegistry([new ReadFile]);
        $tool = new ToolSearch($registry);

        $result = (string) $tool->handle(new Request(['query' => 'database migration']));

        $this->assertStringContainsString('No tools matched', $result);
    }

    public function test_respects_max_results(): void
    {
        $registry = new ToolRegistry([
            new ReadFile,
            new EditFile,
            new GrepFiles,
        ]);

        $tool = new ToolSearch($registry);
        $result = (string) $tool->handle(new Request([
            'query' => 'file',
            'max_results' => 1,
        ]));

        // Should only show one tool's section
        $matches = substr_count($result, '## ');
        $this->assertEquals(1, $matches);
    }
}
