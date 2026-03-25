## ADDED Requirements

### Requirement: RunCommand executes shell commands
The tool SHALL accept a command string and execute it via Laravel's Process facade. It SHALL return stdout, stderr, and exit code. It SHALL enforce a configurable timeout (default 120 seconds). It SHALL restrict execution to the configured `basePath` as working directory.

#### Scenario: Successful command
- **WHEN** RunCommand is called with a valid command
- **THEN** it executes the command, returns stdout and stderr, and indicates the exit code

#### Scenario: Command times out
- **WHEN** RunCommand is called and the command exceeds the configured timeout
- **THEN** it kills the process and returns an error indicating the timeout was exceeded

#### Scenario: Command fails
- **WHEN** RunCommand is called and the command exits with a non-zero code
- **THEN** it returns stdout, stderr, and the non-zero exit code

### Requirement: RunCommand supports command allow and deny lists
The tool SHALL accept optional `allow` and `deny` arrays of glob patterns. If `allow` is set, only matching commands SHALL execute. If `deny` is set, matching commands SHALL be rejected. Deny takes precedence over allow.

#### Scenario: Command matches deny list
- **WHEN** RunCommand is called with a command matching a deny pattern (e.g., `rm -rf *`)
- **THEN** it returns an error indicating the command is not permitted

#### Scenario: Command not in allow list
- **WHEN** RunCommand is configured with an allow list and the command does not match any pattern
- **THEN** it returns an error indicating the command is not permitted

#### Scenario: Allow and deny overlap
- **WHEN** a command matches both an allow and deny pattern
- **THEN** deny takes precedence and the command is rejected

### Requirement: RunCommand supports sync, async, and queued execution
The tool SHALL support an `executor` configuration with three modes: `sync`, `async`, and `queued`. In sync mode it blocks and returns output directly. In async mode it starts a background process and returns a task ID. In queued mode it dispatches a Laravel job and returns a task ID. All modes SHALL create a task entry in the configured TaskStore.

#### Scenario: Sync execution
- **WHEN** RunCommand is configured with executor=sync
- **THEN** it blocks until the command completes and returns the output directly

#### Scenario: Async execution
- **WHEN** RunCommand is configured with executor=async
- **THEN** it starts the process in the background, creates a task entry, and returns the task ID

#### Scenario: Queued execution
- **WHEN** RunCommand is configured with executor=queued
- **THEN** it dispatches a job to the queue, creates a task entry, and returns the task ID

#### Scenario: Background task output retrieval
- **WHEN** a command was run with executor=async or executor=queued and the LLM calls TaskOutput with the task ID
- **THEN** it returns the current output of the running or completed process

### Requirement: RunCommand supports configurable timeout and idle timeout
The tool SHALL accept `timeout` (max total execution time) and `idleTimeout` (max time without output) parameters, both in seconds.

#### Scenario: Idle timeout exceeded
- **WHEN** a running command produces no output for longer than the configured idleTimeout
- **THEN** it kills the process and returns an error indicating the idle timeout was exceeded
