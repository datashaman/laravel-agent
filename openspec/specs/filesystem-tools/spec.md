## ADDED Requirements

### Requirement: ReadFile reads file contents by path
The tool SHALL accept an absolute file path and return the file contents with line numbers. It SHALL support `offset` and `limit` parameters to read specific line ranges. It SHALL reject paths outside the configured `basePath`. It SHALL return a clear error for nonexistent files. It SHALL detect binary files and return a message rather than binary content.

#### Scenario: Read entire file
- **WHEN** ReadFile is called with a valid file path within basePath
- **THEN** it returns the file contents prefixed with line numbers starting at 1

#### Scenario: Read with offset and limit
- **WHEN** ReadFile is called with offset=10 and limit=20
- **THEN** it returns lines 10 through 29 of the file with correct line numbers

#### Scenario: Path outside basePath
- **WHEN** ReadFile is called with a path outside the configured basePath
- **THEN** it returns an error message indicating the path is not allowed

#### Scenario: File does not exist
- **WHEN** ReadFile is called with a nonexistent path
- **THEN** it returns an error message stating the file does not exist

#### Scenario: Binary file detected
- **WHEN** ReadFile is called on a binary file
- **THEN** it returns a message indicating the file is binary and cannot be displayed

### Requirement: WriteFile creates or overwrites files
The tool SHALL accept a file path and content string, and write the content to that path. It SHALL create parent directories if they do not exist. It SHALL reject paths outside the configured `basePath`. The tool description SHALL instruct the LLM to prefer EditFile for modifying existing files.

#### Scenario: Write new file
- **WHEN** WriteFile is called with a path that does not exist
- **THEN** it creates the file with the given content and any necessary parent directories

#### Scenario: Overwrite existing file
- **WHEN** WriteFile is called with a path that already exists
- **THEN** it overwrites the file with the new content

#### Scenario: Path outside basePath
- **WHEN** WriteFile is called with a path outside basePath
- **THEN** it returns an error message indicating the path is not allowed

### Requirement: EditFile performs exact string replacement in files
The tool SHALL accept `file_path`, `old_string`, `new_string`, and optional `replace_all` boolean. It SHALL find and replace `old_string` with `new_string` in the file. It SHALL enforce that `old_string` is unique in the file unless `replace_all` is true. It SHALL reject edits where `old_string` equals `new_string`. It SHALL reject empty `old_string`. It SHALL reject paths outside `basePath`.

#### Scenario: Unique match replacement
- **WHEN** EditFile is called and old_string appears exactly once in the file
- **THEN** it replaces old_string with new_string and returns a success message

#### Scenario: Multiple matches without replace_all
- **WHEN** EditFile is called and old_string appears more than once and replace_all is false
- **THEN** it returns an error listing the number of occurrences and their line numbers

#### Scenario: Multiple matches with replace_all
- **WHEN** EditFile is called with replace_all=true and old_string appears multiple times
- **THEN** it replaces all occurrences and returns a summary of changes made

#### Scenario: String not found with fuzzy suggestions
- **WHEN** EditFile is called and old_string is not found in the file
- **THEN** it returns an error with suggestions for similar strings found in the file, including line numbers and descriptions of differences (whitespace, case)

#### Scenario: old_string equals new_string
- **WHEN** EditFile is called with identical old_string and new_string
- **THEN** it returns an error indicating no change would be made

#### Scenario: Empty old_string
- **WHEN** EditFile is called with an empty old_string
- **THEN** it returns an error indicating old_string must not be empty

### Requirement: GlobFiles finds files by pattern
The tool SHALL accept a glob pattern and optional base directory, and return matching file paths sorted by modification time (most recent first). It SHALL support standard glob syntax including `**` for recursive matching. It SHALL restrict results to within `basePath`.

#### Scenario: Pattern matches files
- **WHEN** GlobFiles is called with pattern `**/*.php`
- **THEN** it returns all PHP files under basePath sorted by modification time descending

#### Scenario: No matches
- **WHEN** GlobFiles is called with a pattern that matches no files
- **THEN** it returns a message indicating no files matched the pattern

#### Scenario: Subdirectory search
- **WHEN** GlobFiles is called with a path parameter restricting the search directory
- **THEN** it only searches within that subdirectory (still within basePath)

### Requirement: GrepFiles searches file contents with regex
The tool SHALL accept a regex pattern and return matching results. It SHALL support output modes: `content` (matching lines with context), `files_with_matches` (file paths only), and `count` (match counts per file). It SHALL support context lines (`-A`, `-B`, `-C`), line numbers, glob filtering, file type filtering, head limits, and case-insensitive search. It SHALL restrict results to within `basePath`.

#### Scenario: Content mode with context
- **WHEN** GrepFiles is called with output_mode=content and context=2
- **THEN** it returns matching lines with 2 lines of context before and after each match, with line numbers

#### Scenario: Files with matches mode
- **WHEN** GrepFiles is called with output_mode=files_with_matches
- **THEN** it returns only the file paths containing matches

#### Scenario: Glob filter
- **WHEN** GrepFiles is called with glob=`*.php`
- **THEN** it only searches files matching that glob pattern

#### Scenario: Case insensitive search
- **WHEN** GrepFiles is called with case_insensitive=true
- **THEN** it matches regardless of case

#### Scenario: Head limit
- **WHEN** GrepFiles is called with head_limit=10
- **THEN** it returns at most 10 results
