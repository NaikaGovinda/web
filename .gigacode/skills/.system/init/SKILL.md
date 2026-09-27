---
name: init
description: Generate or update GIGACODE.md in the project root as a contributor guide for this repository.
disable-model-invocation: true
---

# Init

Generate or update a file at `GIGACODE.md` in the project root that serves as a contributor guide for this repository.

Your goal is to produce a clear, concise, and well-structured document with descriptive headings and actionable explanations for each section.

Follow the outline below, but adapt as needed — add sections if relevant, and omit those that do not apply to this project.

## Document Requirements

- Title the document "Repository Guidelines".
- Use Markdown headings (`#`, `##`, etc.) for structure.
- Keep the document concise. 200-400 words is optimal.
- Keep explanations short, direct, and specific to this repository.
- Provide examples where helpful (commands, directory paths, naming patterns).
- Maintain a professional, instructional tone.

## Recommended Sections

### Project Structure & Module Organization

- Outline the project structure, including where the source code, tests, and assets are located.

### Build, Test, and Development Commands

- List key commands for building, testing, and running locally.
- Briefly explain what each command does.

### Coding Style & Naming Conventions

- Specify indentation rules, language-specific style preferences, and naming patterns.
- Include any formatting or linting tools used.

### Testing Guidelines

- Identify testing frameworks and coverage requirements.
- State test naming conventions and how to run tests.

### Commit & Pull Request Guidelines

- Summarize commit message conventions found in the project's Git history.
- Outline pull request requirements if they are evident in the repository.

### Optional

Add other sections if relevant, such as:

- Security & Configuration Tips
- Architecture Overview
- Agent-Specific Instructions

## Additional Rules

- If `GIGACODE.md` already exists in the project root, update it instead of replacing useful repository-specific content.
- Keep the content grounded in the actual repository.
- Do not include generic advice unless it clearly applies to this project.

## Output

Write the final result to:

`GIGACODE.md` (in the project root)
