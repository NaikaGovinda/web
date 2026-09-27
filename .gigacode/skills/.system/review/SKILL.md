---
name: review
description: Use this skill to review a pull request, diff, files, or recent code changes for high-signal issues only; focus on definite bugs, clear project rule violations, and compile or parse failures, and ignore style nitpicks or speculative concerns.
---

# Review

Review the provided pull request, diff, files, or recent code changes.

Only use tools when needed to complete the review. Review only the requested scope. If no scope is provided, review recently modified code.

Follow this process:

1. Decide whether review is needed.

Do not perform a full review if any of the following are true:
- the changes are trivial
- the changes are obviously correct
- the changes are generated
- the same changes were already reviewed in this session

2. Gather the project rules and conventions that apply to the changed code.

This may include:
- repository guidelines
- local rule files
- review instructions
- language or framework conventions used in the project

3. Briefly summarize what changed.

4. Review the changes carefully.

Focus on:
- project rule compliance
- bugs introduced by the changes
- incorrect behavior in the changed code
- security issues
- surrounding context only when needed to validate a finding

5. Report only high-signal issues.

Flag an issue only if:
- the code will fail to compile, parse, or resolve references
- the code will definitely produce incorrect results
- the change clearly violates an applicable project rule

Do not flag:
- style issues
- subjective improvements
- speculative concerns
- issues that depend on unclear runtime conditions
- pre-existing issues not introduced by the changes
- minor nitpicks
- issues that standard linting would catch
- general test coverage concerns unless explicitly required by project rules

If you are not confident that an issue is real, do not report it.

False positives reduce trust and waste time.

6. Validate every finding before reporting it.

For each finding, confirm that:
- it is real
- it is introduced by the reviewed changes
- the cited project rule applies, if relevant

7. Remove any finding that is not fully validated.

8. Produce the final review.

If issues are found, provide:
- a short summary
- a list of confirmed findings

For each finding, include:
- what is wrong
- why it matters
- where it is
- the smallest reasonable fix

If no issues are found, say exactly:

`No issues found. Checked for bugs and project-rule compliance.`

Be concise, precise, and action-oriented. Focus on changed code. Do not invent missing context. Do not report duplicate findings.
