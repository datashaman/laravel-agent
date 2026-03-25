## Why

Laravel 12+ developers building AI agents with `laravel/ai` need filesystem, shell, and task management tools comparable to what Claude Code provides. Today, `laravel/ai` ships with `WebSearch`, `WebFetch`, and `FileSearch`, but nothing for reading/writing/editing local files, executing commands, managing background tasks, or discovering tools at runtime. Without these, any agent that needs to interact with a codebase or system is limited to what the developer hand-builds.

## What Changes

- **14 new Tool implementations** conforming to `laravel/ai`'s `Tool` interface, organized into four groups:
  - **Filesystem**: `ReadFile`, `WriteFile`, `EditFile`, `GlobFiles`, `GrepFiles` — full codebase interaction with path sandboxing and rich error messages
  - **Execution**: `RunCommand` — shell command execution via Laravel's Process facade with sync, async, and queued execution modes
  - **Task Management**: `TaskCreate`, `TaskGet`, `TaskList`, `TaskOutput`, `TaskStop`, `TaskUpdate`, `TodoWrite` (sugar) — let the LLM organize its own work and track background processes
  - **Meta**: `ToolSearch` — deferred tool discovery so agents can start lean and load tools on demand
- **TaskStore interface** with `InMemory`, `Cache`, and `Database` drivers for task persistence
- **ToolRegistry** — a searchable collection of tools that `ToolSearch` queries against
- **ProcessManager** — thin bridge between Laravel's `Process` facade and the TaskStore for background command tracking
- All tools have **Claude Code-quality descriptions** baked in as defaults (behavioral guidance for the LLM), overridable per-instance
- All filesystem/execution tools accept a `basePath` for sandboxing, plus tool-specific configuration via constructor parameters

## Capabilities

### New Capabilities
- `filesystem-tools`: ReadFile, WriteFile, EditFile, GlobFiles, GrepFiles — file interaction with sandboxing, uniqueness validation, fuzzy match suggestions, and rich error messages
- `execution-tools`: RunCommand with sync/async/queued execution modes, timeout/idle-timeout, command allow/deny lists, and background process tracking
- `task-management`: TaskCreate, TaskGet, TaskList, TaskOutput, TaskStop, TaskUpdate, TodoWrite — ephemeral and persistent task tracking with pluggable storage drivers
- `tool-discovery`: ToolSearch and ToolRegistry for deferred tool loading — agents start with a minimal set and discover more at runtime

### Modified Capabilities

(none — greenfield project)

## Impact

- **Dependencies**: `laravel/ai` (peer), `laravel/framework` ^12.0 (for Process facade, Cache, Database, Queue)
- **Package**: Published as `datashaman/laravel-agent` on Packagist
- **APIs**: All tools implement `Laravel\Ai\Contracts\Tool` — no custom interfaces for tool consumers
- **Infrastructure**: TaskStore drivers may require cache or database depending on chosen driver; queued execution requires a queue worker
