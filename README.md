# Laravel Agent Tools

Claude Code-equivalent tools for [laravel/ai](https://github.com/laravel/ai) agents. Filesystem, shell, task management, and tool discovery.

## Requirements

- PHP 8.3+
- Laravel 12+ or 13+
- `laravel/ai` ^0.3

## Installation

```bash
composer require datashaman/laravel-agent-tools
```

The service provider is auto-discovered. To publish the config:

```bash
php artisan vendor:publish --tag=laravel-agent-config
```

For the database task store, publish and run the migration:

```bash
php artisan vendor:publish --tag=laravel-agent-migrations
php artisan migrate
```

## Tools

### Filesystem

| Tool | Description |
|------|-------------|
| `ReadFile` | Read file contents with line numbers, offset/limit, binary detection |
| `WriteFile` | Create or overwrite files with automatic parent directory creation |
| `EditFile` | Exact string replacement with uniqueness validation and fuzzy-match error suggestions |
| `GlobFiles` | Find files by glob pattern, sorted by modification time |
| `GrepFiles` | Search file contents with regex, multiple output modes, context lines, filtering |

### Execution

| Tool | Description |
|------|-------------|
| `RunCommand` | Execute shell commands with sync, async, or queued modes. Supports timeout, idle timeout, and command allow/deny lists |

### Task Management

| Tool | Description |
|------|-------------|
| `TaskCreate` | Create a new task |
| `TaskGet` | Retrieve task details by ID |
| `TaskList` | List tasks with optional status filtering |
| `TaskOutput` | Get output from a background task |
| `TaskStop` | Kill a running background task |
| `TaskUpdate` | Update task properties or delete a task |
| `TodoWrite` | Batch-manage the full task list (sugar over individual task tools) |

### Meta

| Tool | Description |
|------|-------------|
| `ToolSearch` | Discover and load deferred tools from a `ToolRegistry` |

## Usage

All tools implement `Laravel\Ai\Contracts\Tool` and work with any `laravel/ai` agent:

```php
use DataShaman\LaravelAgent\Tools\Filesystem\ReadFile;
use DataShaman\LaravelAgent\Tools\Filesystem\WriteFile;
use DataShaman\LaravelAgent\Tools\Filesystem\EditFile;
use DataShaman\LaravelAgent\Tools\Filesystem\GlobFiles;
use DataShaman\LaravelAgent\Tools\Filesystem\GrepFiles;
use DataShaman\LaravelAgent\Tools\Execution\RunCommand;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class CodeAssistant implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private string $projectPath,
    ) {}

    public function instructions(): string
    {
        return 'You are a code assistant with full filesystem and shell access.';
    }

    public function tools(): iterable
    {
        return [
            new ReadFile(basePath: $this->projectPath),
            new WriteFile(basePath: $this->projectPath),
            new EditFile(basePath: $this->projectPath),
            new GlobFiles(basePath: $this->projectPath),
            new GrepFiles(basePath: $this->projectPath),
            new RunCommand(
                basePath: $this->projectPath,
                timeout: 30,
                allow: ['git *', 'composer *', 'php *'],
                deny: ['rm -rf *', 'sudo *'],
            ),
        ];
    }
}
```

### Task Management

```php
use DataShaman\LaravelAgent\Support\InMemoryTaskStore;
use DataShaman\LaravelAgent\Tools\Tasks\TaskCreate;
use DataShaman\LaravelAgent\Tools\Tasks\TaskList;
use DataShaman\LaravelAgent\Tools\Tasks\TaskUpdate;

$store = new InMemoryTaskStore;

$tools = [
    new TaskCreate($store),
    new TaskList($store),
    new TaskUpdate($store),
    // ... other tools
];
```

### Tool Discovery

Start with a minimal tool set and let the agent discover more at runtime:

```php
use DataShaman\LaravelAgent\Support\ToolRegistry;
use DataShaman\LaravelAgent\Tools\Meta\ToolSearch;

$registry = new ToolRegistry([
    new ReadFile(basePath: $path),
    new WriteFile(basePath: $path),
    new EditFile(basePath: $path),
    new GlobFiles(basePath: $path),
    new GrepFiles(basePath: $path),
    new RunCommand(basePath: $path),
]);

// Agent starts with just ReadFile, GlobFiles, and ToolSearch
$tools = [
    new ReadFile(basePath: $path),
    new GlobFiles(basePath: $path),
    new ToolSearch($registry),
];
```

### Background Commands

```php
use DataShaman\LaravelAgent\Support\InMemoryTaskStore;
use DataShaman\LaravelAgent\Support\ProcessManager;
use DataShaman\LaravelAgent\Tools\Execution\RunCommand;

$store = new InMemoryTaskStore;
$processManager = new ProcessManager($store);

// Async: background process via Laravel Process::start()
$tool = new RunCommand(
    basePath: $path,
    executor: 'async',
    taskStore: $store,
    processManager: $processManager,
);

// Queued: dispatched as a Laravel job
$tool = new RunCommand(
    basePath: $path,
    executor: 'queued',
    taskStore: $store,
);
```

## Configuration

All tools are configured via constructor parameters. No global config required.

The `config/laravel-agent.php` file provides defaults for the service provider bindings:

| Key | Default | Description |
|-----|---------|-------------|
| `task_store` | `memory` | Task store driver: `memory`, `cache`, `database` |
| `executor` | `async` | RunCommand background executor: `sync`, `async`, `queued` |
| `cache.store` | `null` | Cache store for CacheTaskStore |
| `cache.prefix` | `laravel-agent:tasks:` | Cache key prefix |
| `cache.ttl` | `3600` | Cache TTL in seconds |
| `database.connection` | `null` | Database connection for DatabaseTaskStore |
| `database.table` | `agent_tasks` | Table name for DatabaseTaskStore |

## Sandboxing

All filesystem and execution tools accept a `basePath` parameter that restricts operations to that directory. Paths outside `basePath` are rejected.

RunCommand additionally supports `allow` and `deny` glob patterns for command filtering. Deny takes precedence over allow.

## Custom Descriptions

Every tool ships with Claude Code-quality descriptions that guide LLM behavior. Override them per-instance:

```php
new EditFile(
    basePath: $path,
    description: 'Edit files. No restrictions.',
)
```

## Testing

```bash
composer test
```

Or directly:

```bash
vendor/bin/phpunit
```

## License

MIT
