## Context

`laravel/ai` provides a clean agent framework: `Agent`, `HasTools`, `Tool`, `Promptable`. Tools implement `description()`, `schema()`, and `handle()`. The framework handles the agent loop, LLM communication, and tool dispatch. We're building a set of Tool implementations that give any `laravel/ai` agent Claude Code-level capabilities for filesystem interaction, shell execution, task management, and tool discovery.

The package targets Laravel 12+ and must work in both CLI contexts (artisan commands) and server-side contexts (queue jobs, HTTP requests in SaaS environments).

## Goals / Non-Goals

**Goals:**
- Provide 14 production-quality Tool implementations conforming to `Laravel\Ai\Contracts\Tool`
- Each tool is independently usable — no requirement to use the full set
- Tools are configurable via constructor parameters (basePath, timeout, allow/deny lists, descriptions)
- Tool descriptions include Claude Code-quality behavioral guidance for the LLM, overridable per-instance
- Filesystem tools enforce path sandboxing via `basePath`
- RunCommand supports sync, async, and queued execution modes via Laravel's Process facade and Queue system
- TaskStore is pluggable with InMemory, Cache, and Database drivers
- ToolSearch enables deferred tool loading from a ToolRegistry

**Non-Goals:**
- Not building an agent framework (that's `laravel/ai`'s job)
- Not building a permission/approval system (developers build their own or wrap tools)
- Not building a CLI experience or interactive terminal
- Not providing LLM provider configuration (that's `laravel/ai`'s job)
- Not duplicating tools `laravel/ai` already ships (WebSearch, WebFetch, FileSearch)

## Decisions

### 1. Tools are plain classes with constructor configuration

**Decision**: Each tool is a standalone class. Configuration via constructor parameters, no config files, no service container bindings required.

```php
new ReadFile(basePath: '/home/user/project')
new RunCommand(basePath: '/home/user/project', timeout: 30, executor: 'async')
```

**Why over config file approach**: Tools may be instantiated multiple times with different configs (e.g., two RunCommand instances with different sandboxes). Constructor injection is explicit, testable, and doesn't require a service provider.

**Alternative considered**: Laravel config + service provider. Rejected because it couples tool configuration to the application container, making it harder to use tools in isolation or with different configs per agent.

### 2. Behavioral guidance lives in tool descriptions, not enforcement code

**Decision**: Claude Code's behavioral rules ("read before editing", "prefer Edit over Write") are baked into each tool's default `description()` return value. The LLM reads these as part of the tool schema. Mechanical constraints (uniqueness, path validation) are enforced in code.

**Why**: Keeps the tool implementations simple. The LLM follows description guidance naturally. Developers can override descriptions to change LLM behavior without forking.

### 3. RunCommand execution modes: sync, async, queued

**Decision**: RunCommand wraps Laravel's `Process` facade for sync/async and dispatches a job for queued mode. All three modes create a task entry in the TaskStore.

- **sync**: `Process::timeout($t)->run($cmd)` — blocks, returns output directly
- **async**: `Process::timeout($t)->start($cmd)` — returns task ID, LLM polls via TaskOutput
- **queued**: Dispatches a `RunCommandJob` — returns task ID, durable via Laravel's queue

**Why not just async**: Queued execution survives process restarts, supports retries, and distributes across workers. Essential for SaaS environments where the agent loop itself may run in a queue job.

### 4. TaskStore as a pluggable interface

**Decision**: Tasks (both LLM-created organizational tasks and background process tasks) share a single `TaskStore` interface with three drivers.

```
TaskStore (interface)
├── InMemoryTaskStore   — default, ephemeral, zero config
├── CacheTaskStore      — survives requests via Laravel Cache
└── DatabaseTaskStore   — fully persistent, queryable
```

**Why a shared store**: Background processes and organizational tasks are both "work the agent is tracking." The LLM uses the same TaskList/TaskGet tools for both. Splitting them would mean two sets of tools or routing logic.

### 5. EditFile uses exact string matching with rich error messages

**Decision**: `old_string` must match exactly (including whitespace). On failure, the tool returns actionable context: occurrence count with line numbers, or fuzzy-match suggestions when not found.

**Why not fuzzy matching**: Ambiguity. If the LLM says "replace X" and we fuzzy-match to something slightly different, the edit may be wrong. Exact matching + good error messages lets the LLM self-correct in one turn.

### 6. ToolRegistry is a simple searchable collection

**Decision**: `ToolRegistry` holds Tool instances and supports keyword search against tool names and descriptions. `ToolSearch` queries it and returns matching tool schemas. No lazy loading, no plugin system.

**Why simple**: The registry just needs to answer "what tools match this query?" The developer populates it when wiring up the agent. No need for auto-discovery or reflection.

### 7. TodoWrite is sugar over TaskCreate/TaskUpdate

**Decision**: `TodoWrite` accepts a batch of tasks (the full checklist) and resolves them against the TaskStore — creating, updating, or removing as needed. Internally it calls the same store methods as the individual Task tools.

**Why**: Claude Code has both patterns. TodoWrite is useful for non-interactive/batch contexts where the LLM wants to set the whole task list at once rather than making individual calls.

## Risks / Trade-offs

**[Risk] LLM ignores description guidance** → Descriptions are guidance, not enforcement. Critical safety constraints (path sandboxing, command deny lists) are enforced in code. Behavioral guidance that's ignored just results in suboptimal tool usage, not dangerous behavior.

**[Risk] Queued RunCommand loses process state on worker restart** → Mitigation: queued commands use Laravel's job retry/failure mechanisms. TaskStore records the final state. Developers can configure retry behavior via standard Laravel job config.

**[Risk] InMemoryTaskStore loses state between requests** → This is by design for the default driver. Documentation will clearly state when to use Cache or Database drivers. The LLM's task management is ephemeral by default, persistent by opt-in.

**[Risk] EditFile fuzzy matching gives bad suggestions** → Mitigation: fuzzy matching is only used in error messages to help the LLM self-correct, never for actual matching. Levenshtein distance or similar, with a threshold to avoid noise.

**[Trade-off] No built-in permission system** → Deliberate. A permission system is inherently opinionated about execution context (CLI prompt vs webhook vs queue). Developers wrap tools with their own permission logic. The tools stay general-purpose.
