## ADDED Requirements

### Requirement: ToolRegistry holds a searchable collection of tools
The `ToolRegistry` SHALL accept an array of Tool instances. It SHALL support searching by keyword against tool names and descriptions. It SHALL return matching tools with their full schemas.

#### Scenario: Search by keyword
- **WHEN** ToolRegistry is searched with query "edit file"
- **THEN** it returns tools whose name or description matches, ranked by relevance

#### Scenario: No matches
- **WHEN** ToolRegistry is searched with a query that matches no tools
- **THEN** it returns an empty result with a message suggesting query refinements

#### Scenario: Exact name lookup
- **WHEN** ToolRegistry is searched with an exact tool name
- **THEN** it returns that specific tool

### Requirement: ToolSearch discovers and loads deferred tools
The `ToolSearch` tool SHALL accept a search query and return matching tool schemas from its configured ToolRegistry. The returned schemas SHALL be in the format expected by the LLM provider so the tools become available for subsequent calls.

#### Scenario: Discover a tool
- **WHEN** ToolSearch is called with query "grep" and the registry contains GrepFiles
- **THEN** it returns the GrepFiles tool schema including name, description, and parameter definitions

#### Scenario: Multiple matches
- **WHEN** ToolSearch is called with query "file" and multiple file-related tools exist
- **THEN** it returns all matching tool schemas, limited by an optional max_results parameter

#### Scenario: Tool not in registry
- **WHEN** ToolSearch is called with a query that matches no registered tools
- **THEN** it returns a message indicating no tools matched and suggests alternative queries
