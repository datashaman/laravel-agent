## ADDED Requirements

### Requirement: TaskStore interface with pluggable drivers
The package SHALL provide a `TaskStore` interface with methods: `create`, `get`, `list`, `update`, `output`, `stop`, `delete`. It SHALL ship with three drivers: `InMemoryTaskStore` (default, ephemeral), `CacheTaskStore` (uses Laravel Cache), and `DatabaseTaskStore` (uses Laravel database). The driver SHALL be injectable into task tools via constructor.

#### Scenario: InMemory driver stores tasks for session duration
- **WHEN** tasks are created using InMemoryTaskStore
- **THEN** they are available for the lifetime of the PHP process and lost when the process ends

#### Scenario: Cache driver survives requests
- **WHEN** tasks are created using CacheTaskStore
- **THEN** they are persisted in the configured Laravel cache store and survive across requests

#### Scenario: Database driver persists durably
- **WHEN** tasks are created using DatabaseTaskStore
- **THEN** they are stored in a database table and survive indefinitely until deleted

### Requirement: TaskCreate creates tasks
The tool SHALL accept a task title, optional description, and optional status. It SHALL create a task entry in the TaskStore and return the task with its generated ID.

#### Scenario: Create a task
- **WHEN** TaskCreate is called with title="Refactor auth module"
- **THEN** a task is created in the store with a unique ID and the given title, and the task details are returned

### Requirement: TaskGet retrieves task details
The tool SHALL accept a task ID and return the full task record including ID, title, description, status, and output (if any).

#### Scenario: Get existing task
- **WHEN** TaskGet is called with a valid task ID
- **THEN** it returns the full task record

#### Scenario: Task not found
- **WHEN** TaskGet is called with a nonexistent ID
- **THEN** it returns an error indicating the task was not found

### Requirement: TaskList lists tasks with filtering
The tool SHALL return all tasks from the TaskStore. It SHALL support optional status filtering.

#### Scenario: List all tasks
- **WHEN** TaskList is called with no filters
- **THEN** it returns all tasks in the store with their current status

#### Scenario: Filter by status
- **WHEN** TaskList is called with status=in_progress
- **THEN** it returns only tasks with that status

### Requirement: TaskOutput retrieves output from background tasks
The tool SHALL accept a task ID and return the current output (stdout/stderr) of the associated background process. It SHALL indicate whether the process is still running or has completed.

#### Scenario: Running process output
- **WHEN** TaskOutput is called for a task with a running background process
- **THEN** it returns the output produced so far and indicates the process is still running

#### Scenario: Completed process output
- **WHEN** TaskOutput is called for a task whose process has completed
- **THEN** it returns the full output and indicates the process has finished with its exit code

### Requirement: TaskStop kills a background task
The tool SHALL accept a task ID and terminate the associated background process. It SHALL update the task status to indicate it was stopped.

#### Scenario: Stop a running task
- **WHEN** TaskStop is called for a task with a running process
- **THEN** it kills the process and updates the task status to stopped

#### Scenario: Stop a non-running task
- **WHEN** TaskStop is called for a task that is not running
- **THEN** it returns a message indicating the task is not currently running

### Requirement: TaskUpdate modifies task properties
The tool SHALL accept a task ID and optional updates for status, title, description, and dependencies. It SHALL also support deleting a task.

#### Scenario: Update task status
- **WHEN** TaskUpdate is called with status=completed
- **THEN** the task status is updated in the store

#### Scenario: Delete a task
- **WHEN** TaskUpdate is called with delete=true
- **THEN** the task is removed from the store

### Requirement: TodoWrite batch-manages the task list
The tool SHALL accept an array of task objects (each with title, status, optional description) and reconcile the full task list in the store — creating new tasks, updating existing ones, and removing tasks not in the list. It SHALL be sugar over the same TaskStore used by the individual Task tools.

#### Scenario: Set full task list
- **WHEN** TodoWrite is called with a list of 5 tasks
- **THEN** the store contains exactly those 5 tasks with the specified statuses, removing any previously existing tasks not in the list

#### Scenario: Update partial statuses
- **WHEN** TodoWrite is called with a list where 2 tasks have updated statuses
- **THEN** those 2 tasks are updated and all others remain unchanged
