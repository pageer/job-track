---
name: review
description: Use when the user asks for a code review, PR review, or review of a git diff or branch (e.g. "review this", "review my PR", "review the diff", "review branch X"). Produces a structured, comment-style review of changes.
---

# Review

Perform a structured code review of git changes. Read-only: never edit code, commit, or post comments to GitHub unless explicitly asked.

## 1. Determine the review scope

Resolve what to review, in this order of preference:

1. An explicit argument: a branch name, PR number/URL, commit range, or file paths.
2. Uncommitted work: `git status` + `git diff` (staged and unstaged) plus `git diff main...HEAD` if commits exist on the branch.
3. The current branch against its merge base: `git merge-base HEAD main` (fall back to `master` or the remote default branch), then `git diff <merge-base>...HEAD`.

For a PR, prefer `gh pr diff <number>` and `gh pr view <number>` for title/description. List the changed files with `git diff --stat` before reading content.

## 2. Read with context

- Read the **full file** for each changed file, not just the hunks — most real defects live in the interaction between a change and surrounding code.
- Check linked issues, PR description, and existing tests to understand intent.
- Look at neighboring files for house conventions (naming, error handling, layering) before flagging style.

## 3. Review checklist

Prioritize in this order; only report items that actually apply:

1. **Correctness** — logic errors, off-by-one, wrong conditions, unhandled null/empty/edge cases, race conditions, broken invariants.
2. **Security** — injection, authz/authn gaps (in this repo: user scoping on every entity, CSRF on non-GET API calls), secret leakage, unsafe deserialization.
3. **Contract breakage** — API response shape, serializer groups, frontend/backend field mismatches (per AGENTS.md).
4. **Data integrity** — migration safety, cascading deletes, missing user scoping, N+1 queries.
5. **Tests** — does the change have tests? Do they cover the risky paths? Are existing tests updated?
6. **Style/conventions** — only when it deviates from what the surrounding codebase does.

## 4. Output format

Group findings by severity. For each finding include `file_path:line_number`, a one-line explanation, and a concrete suggested fix. Be specific — "consider refactoring" is not a finding.

```
## Review: <branch/PR/diff scope>

### Blocking
- `src/Foo.php:42` — <what's wrong and why it matters> → <suggested fix>

### Suggestions
- ...

### Nits (optional)
- ...

### Summary
<2-3 sentences: overall assessment and whether it's safe to merge>
```

If there are no findings in a severity group, omit it. If the change is clean, say so plainly rather than inventing issues.

## 5. Ground rules

- Findings must reference real code you have read; never speculate about file contents.
- Do not restate the diff. The reader has seen it.
- Match severity to actual impact: a missing test is not blocking; a broken user-scope check is.
- One finding per issue — don't repeat the same problem for every occurrence unless each needs a distinct fix.
