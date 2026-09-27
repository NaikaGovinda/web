#!/usr/bin/env python3
"""Validate a skill directory structure and frontmatter."""

import sys
import re
import yaml
from pathlib import Path


def validate_skill(skill_path):
    skill_path = Path(skill_path)
    skill_md = skill_path / 'SKILL.md'

    if not skill_md.exists():
        return False, "SKILL.md not found"

    content = skill_md.read_text()
    if not content.startswith('---'):
        return False, "No YAML frontmatter found"

    match = re.match(r'^---\n(.*?)\n---', content, re.DOTALL)
    if not match:
        return False, "Invalid frontmatter format"

    try:
        fm = yaml.safe_load(match.group(1))
        if not isinstance(fm, dict):
            return False, "Frontmatter must be a YAML dictionary"
    except yaml.YAMLError as e:
        return False, f"Invalid YAML: {e}"

    if 'name' not in fm:
        return False, "Missing 'name' in frontmatter"
    if 'description' not in fm:
        return False, "Missing 'description' in frontmatter"

    name = str(fm['name']).strip()
    if name and not re.match(r'^[a-z0-9]+(-[a-z0-9]+)*$', name):
        return False, f"Name '{name}' must be hyphen-case (lowercase, digits, hyphens, no leading/trailing/consecutive hyphens)"

    desc = str(fm.get('description', '')).strip()
    if desc and len(desc) > 1024:
        return False, f"Description too long ({len(desc)} chars, max 1024)"

    return True, "Valid"


if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: validate_skill.py <skill-directory>")
        sys.exit(1)

    valid, msg = validate_skill(sys.argv[1])
    print(msg)
    sys.exit(0 if valid else 1)
