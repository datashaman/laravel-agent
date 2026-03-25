## 1. Package Scaffolding

- [x] 1.1 Initialize composer.json with datashaman/laravel-agent, require laravel/ai and laravel/framework ^12.0
- [x] 1.2 Create package directory structure: src/Tools/, src/Support/, src/Jobs/
- [x] 1.3 Create ServiceProvider with package auto-discovery (publishes config and migrations)
- [x] 1.4 Create config/laravel-agent.php with defaults for task store driver and RunCommand executor

## 2. Support Infrastructure

- [x] 2.1 Implement TaskStore interface (create, get, list, update, output, stop, delete)
- [x] 2.2 Implement InMemoryTaskStore driver
- [x] 2.3 Implement CacheTaskStore driver using Laravel Cache
- [x] 2.4 Implement DatabaseTaskStore driver with migration for tasks table
- [x] 2.5 Implement ProcessManager — bridge between Process facade and TaskStore for background process tracking
- [x] 2.6 Implement ToolRegistry — searchable collection with keyword matching against tool names and descriptions

## 3. Filesystem Tools

- [x] 3.1 Implement ReadFile tool — file reading with line numbers, offset/limit, binary detection, basePath sandboxing
- [x] 3.2 Implement WriteFile tool — file creation/overwrite with parent directory creation, basePath sandboxing
- [x] 3.3 Implement EditFile tool — exact string replacement with uniqueness validation, fuzzy-match error suggestions, replace_all support
- [x] 3.4 Implement GlobFiles tool — pattern matching with recursive support, sorted by modification time, basePath sandboxing
- [x] 3.5 Implement GrepFiles tool — regex search with output modes (content/files_with_matches/count), context lines, glob/type filtering, head limits, case-insensitive support

## 4. Execution Tools

- [x] 4.1 Implement RunCommand tool — sync execution via Process::run() with timeout, basePath as working directory
- [x] 4.2 Add async execution mode — Process::start() with task creation and output tracking
- [x] 4.3 Add queued execution mode — RunCommandJob dispatched to Laravel queue with task tracking
- [x] 4.4 Implement command allow/deny list matching with glob patterns, deny-takes-precedence logic
- [x] 4.5 Add idle timeout support via Process::idleTimeout()

## 5. Task Management Tools

- [x] 5.1 Implement TaskCreate tool — creates task in store, returns task with ID
- [x] 5.2 Implement TaskGet tool — retrieves full task record by ID
- [x] 5.3 Implement TaskList tool — lists all tasks with optional status filtering
- [x] 5.4 Implement TaskOutput tool — retrieves output from background process tasks, indicates running/completed
- [x] 5.5 Implement TaskStop tool — kills background process, updates task status
- [x] 5.6 Implement TaskUpdate tool — updates task properties, supports delete
- [x] 5.7 Implement TodoWrite tool — batch task reconciliation as sugar over TaskStore

## 6. Tool Discovery

- [x] 6.1 Implement ToolSearch tool — queries ToolRegistry, returns matching tool schemas in LLM-consumable format
- [x] 6.2 Add max_results parameter and relevance ranking to ToolSearch

## 7. Tool Descriptions

- [x] 7.1 Write Claude Code-quality default descriptions for all filesystem tools with behavioral guidance
- [x] 7.2 Write default descriptions for RunCommand including safety guidance
- [x] 7.3 Write default descriptions for task management tools
- [x] 7.4 Write default description for ToolSearch explaining deferred loading
- [x] 7.5 Ensure all tools accept optional description override via constructor

## 8. Tests

- [x] 8.1 Unit tests for all TaskStore drivers (InMemory, Cache, Database)
- [x] 8.2 Unit tests for ToolRegistry search and ranking
- [x] 8.3 Unit tests for ReadFile (line ranges, binary detection, sandboxing)
- [x] 8.4 Unit tests for WriteFile (creation, overwrite, directory creation, sandboxing)
- [x] 8.5 Unit tests for EditFile (unique match, multiple matches, not found with fuzzy suggestions, replace_all, validation errors)
- [x] 8.6 Unit tests for GlobFiles (pattern matching, sorting, sandboxing)
- [x] 8.7 Unit tests for GrepFiles (all output modes, context, filtering, limits)
- [x] 8.8 Unit tests for RunCommand (sync/async/queued, timeout, allow/deny lists)
- [x] 8.9 Unit tests for all task management tools against InMemoryTaskStore
- [x] 8.10 Unit tests for ToolSearch against ToolRegistry
- [x] 8.11 Integration test: full agent using all tools with laravel/ai Agent interface
