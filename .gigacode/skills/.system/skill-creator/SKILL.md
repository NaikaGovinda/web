---
name: skill-creator
description: Guide for creating and updating skills that extend agent capabilities with specialized knowledge, workflows, or tool integrations.
---

# Skill Creator

Skills are modular packages that extend agent capabilities with specialized knowledge, workflows, and tools.

## Core Principles

### Be Concise

Context window is shared. Only include information the agent doesn't already have. Prefer examples over explanations.

### Match Freedom to Fragility

- **High freedom** (text instructions): multiple valid approaches, context-dependent
- **Medium freedom** (pseudocode/parameterized scripts): preferred pattern exists, some variation OK
- **Low freedom** (exact scripts): fragile operations, consistency critical

### Skill Structure

```
skill-name/
├── SKILL.md              (required - metadata + instructions)
├── scripts/              (optional - executable code)
├── references/           (optional - docs loaded into context as needed)
└── assets/               (optional - files used in output, not loaded into context)
```

#### SKILL.md

- **Frontmatter** (YAML): `name` and `description` fields. Description determines when the skill triggers — be specific about what it does AND when to use it.
- **Body** (Markdown): Instructions loaded only after triggering.

#### Bundled Resources

- **scripts/**: Deterministic, reusable code. Token-efficient, can execute without loading into context.
- **references/**: Documentation loaded as-needed. Keep SKILL.md lean by moving detailed info here.
- **assets/**: Templates, images, fonts — used in output, never loaded into context.

### Progressive Disclosure

Keep SKILL.md under 500 lines. Split content into reference files when approaching this limit. Always reference split-out files from SKILL.md with clear guidance on when to read them.

**Pattern: Domain-specific organization**
```
skill/
├── SKILL.md (overview + navigation)
└── references/
    ├── domain-a.md
    └── domain-b.md
```
Agent loads only the relevant reference file.

## Skill Location

Skills can be created in two places:

- **Project-scoped**: `.gigacode/skills/<skill-name>/` — available only in that project
- **Global**: `~/.gigacode/skills/<skill-name>/` — available across all projects

If it's not clear from context whether the skill is project-specific or general-purpose, ask the user.

## Skill Creation Process

1. Understand the skill with concrete examples
2. Plan reusable contents (scripts, references, assets)
3. Initialize the skill
4. Implement resources and write SKILL.md
5. Iterate based on real usage

### Step 1: Understand with Examples

Ask clarifying questions:
- What functionality should the skill support?
- Example usage scenarios?
- What triggers the skill?
- Project-scoped or global?

### Step 2: Plan Contents

For each example scenario, identify:
1. What code gets rewritten repeatedly → `scripts/`
2. What schemas/docs get rediscovered each time → `references/`
3. What boilerplate/templates are reused → `assets/`

### Step 3: Initialize

```bash
# Project-scoped
scripts/init_skill.py <skill-name> --path .gigacode/skills

# Global
scripts/init_skill.py <skill-name> --path ~/.gigacode/skills
```

Creates skill directory with SKILL.md template and example resource directories. Skip if skill already exists.

### Step 4: Implement

1. Start with reusable resources (scripts, references, assets)
2. Test scripts by running them
3. Delete unused example files
4. Write SKILL.md:
   - Frontmatter: `name` + `description` (include all trigger conditions in description)
   - Body: instructions for using the skill and its resources
   - Use imperative form
   - Consult `references/workflows.md` for multi-step process patterns
   - Consult `references/output-patterns.md` for output format patterns

### Step 5: Iterate

1. Use the skill on real tasks
2. Notice struggles or inefficiencies
3. Update SKILL.md or resources
4. Test again
