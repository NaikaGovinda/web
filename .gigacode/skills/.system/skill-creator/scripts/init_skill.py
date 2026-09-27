#!/usr/bin/env python3
"""
Initialize a new skill from template.

Usage: init_skill.py <skill-name> --path <output-directory>
"""

import sys
from pathlib import Path

SKILL_TEMPLATE = """---
name: {skill_name}
description: [TODO: What the skill does and when to use it. Include specific triggers/scenarios.]
---

# {skill_title}

[TODO: 1-2 sentences explaining what this skill enables]

## [TODO: First main section]

[TODO: Add content - code samples, decision trees, examples, resource references]

## Resources

- **scripts/**: Executable code for specific operations
- **references/**: Documentation loaded into context as needed
- **assets/**: Files used in output (templates, images, etc.)

Delete unused resource directories.
"""

EXAMPLE_SCRIPT = '''#!/usr/bin/env python3
"""Example helper script for {skill_name}. Replace or delete."""

def main():
    print("Example script for {skill_name}")

if __name__ == "__main__":
    main()
'''

EXAMPLE_REFERENCE = """# Reference Documentation for {skill_title}

Replace with actual reference content or delete if not needed.

Useful for: API docs, schemas, detailed workflow guides, domain knowledge.
"""


def title_case(name):
    return ' '.join(w.capitalize() for w in name.split('-'))


def init_skill(skill_name, path):
    skill_dir = Path(path).resolve() / skill_name

    if skill_dir.exists():
        print(f"Error: {skill_dir} already exists")
        return None

    skill_dir.mkdir(parents=True)
    skill_title = title_case(skill_name)

    (skill_dir / 'SKILL.md').write_text(
        SKILL_TEMPLATE.format(skill_name=skill_name, skill_title=skill_title)
    )

    scripts_dir = skill_dir / 'scripts'
    scripts_dir.mkdir()
    script = scripts_dir / 'example.py'
    script.write_text(EXAMPLE_SCRIPT.format(skill_name=skill_name))
    script.chmod(0o755)

    refs_dir = skill_dir / 'references'
    refs_dir.mkdir()
    (refs_dir / 'reference.md').write_text(
        EXAMPLE_REFERENCE.format(skill_title=skill_title)
    )

    print(f"Skill '{skill_name}' initialized at {skill_dir}")
    return skill_dir


def main():
    if len(sys.argv) < 4 or sys.argv[2] != '--path':
        print("Usage: init_skill.py <skill-name> --path <path>")
        sys.exit(1)

    init_skill(sys.argv[1], sys.argv[3])


if __name__ == "__main__":
    main()
