# Output Patterns

## Template Pattern

Provide templates for output format. Match strictness to needs.

**Strict (API responses, data formats):**

```markdown
## Report structure

ALWAYS use this exact template:

# [Title]
## Executive summary
[One-paragraph overview]
## Key findings
- Finding 1 with data
- Finding 2 with data
## Recommendations
1. Actionable recommendation
2. Actionable recommendation
```

**Flexible (when adaptation is useful):**

```markdown
## Report structure

Sensible default format, use best judgment:

# [Title]
## Executive summary
## Key findings
[Adapt sections based on discoveries]
## Recommendations
[Tailor to context]
```

## Examples Pattern

For output quality dependent on seeing examples, provide input/output pairs:

```markdown
## Commit message format

**Example 1:**
Input: Added user authentication with JWT tokens
Output: feat(auth): implement JWT-based authentication

**Example 2:**
Input: Fixed bug where dates displayed incorrectly
Output: fix(reports): correct date formatting in timezone conversion

Follow this style: type(scope): brief description
```

Examples convey desired style better than descriptions.
