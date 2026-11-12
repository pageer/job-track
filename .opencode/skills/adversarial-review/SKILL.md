---
name: "Adversarial Review"
description: "Use when the user asks for an adversarial, cross-model, multi-model, red-team, or second-opinion code review of a diff, branch, PR, or changed files. Runs independent reviewers from different model families plus an adjudicator, then reports only verified findings. Read-only."
---

# Adversarial Review

Run an independent, multi-model review of a change. The goal is to catch semantic defects the author model and the quality gates miss, while filtering out plausible-but-wrong findings.

This skill is read-only: never edit code, commit, push, or post GitHub comments unless the user explicitly asks.

## 1. Choose the mode

- **quick** (default): two reviewers plus one adjudicator.
  - `review-correctness` — GPT 6 Luna, correctness and edge cases.
  - `review-security` — Claude Haiku 5.5, security, authz, and API contract.
  - `review-adjudicator` — Kimi K3.1 Pro, verifies and deduplicates.
- **deep**: three reviewers plus a non-panel adjudicator.
  - `review-correctness`
  - `review-security`
  - `review-architecture` — Kimi K3.1 Pro, design and test quality.
  - `review-adjudicator-deep` — DeepSeek V4 Pro, verifies and deduplicates.

Use **deep** automatically when the change touches any of:

- auth, sessions, CSRF, or security configuration (`security.yaml`, `ApiCsrfSubscriber.php`)
- user scoping or ownership checks (`backend/src/Controller/*`, repositories, `findOwned*` helpers)
- Doctrine migrations, entity relations, cascades, or serializer groups
- API request/response contracts or frontend/backend field names
- secrets, tokens, external auth (OneDrive), or AI extraction/normalization
- concurrency, background work, or multi-step state transitions
- money or date calculations, or anything where a wrong result is hard to notice

Otherwise use **quick**. If the user explicitly says "quick" or "deep", honor it. If the scope makes the choice genuinely ambiguous, ask once rather than guessing.

## 2. Determine the scope

Resolve what to review, in this order of preference:

1. An explicit argument: a branch name, PR number/URL, commit range, or file paths.
2. Uncommitted work: `git status`, `git diff`, `git diff --cached`, plus `git diff main...HEAD` if commits exist on the branch.
3. The current branch against its merge base: `git merge-base HEAD main` (fall back to `master` or the remote default branch), then `git diff <merge-base>...HEAD`.

For a PR, prefer `gh pr diff <number>` and `gh pr view <number>` for title and description.

Capture:

- `git diff --stat` and the full diff.
- The changed-file list.
- The full contents of each changed file, or clear instructions for the reviewers to read them.
- The relevant tests and the requirement, issue, or acceptance criteria.
- The relevant `AGENTS.md` rules, especially user scoping, CSRF, serializer groups, and the frontend/backend contract.

Do **not** include the author's rationale, commit-message spin, or a summary of why the change is correct. Reviewers should see the code and the requirement, not the defense.

Redact secrets. Never send `.env`, `.env.local`, credentials, tokens, or personal data to reviewers.

## 3. Build the reviewer brief

Every reviewer gets the same neutral context plus its role focus:

- Scope: branch/PR/commit range and what the change is supposed to accomplish.
- The diff and changed-file list.
- Full file contents or instructions to read them.
- Relevant tests and acceptance criteria.
- The role focus below.

Role focus:

- **Correctness:** logic, edge cases, state transitions, concurrency, partial failure, regressions, test gaps.
- **Security/contract:** authn/authz, user scoping, CSRF, injection, secrets, unsafe deserialization, API shape, serializer groups, migrations, data integrity.
- **Architecture (deep only):** design fit, layering, coupling, hidden invariants, simpler or safer alternatives, test quality, performance.

Do not tell reviewers which model wrote the code. Do not ask them to "be harsh" or to "find at least N issues." Explicitly allow `No findings.`

## 4. Launch the reviewers in parallel

Use the `subagent` tool with the agent IDs above. Launch all reviewers in one step so they run concurrently. Foreground is fine; do not use background unless the user asks.

If a reviewer agent is unavailable, stop and tell the user rather than silently substituting a different model.

## 5. Adjudicate

Pass all reviewer reports, the diff, and the changed-file list to the adjudicator agent:

- quick: `review-adjudicator`
- deep: `review-adjudicator-deep`

The adjudicator must:

- Verify every finding against the actual code (read the full files).
- Deduplicate findings that share a root cause.
- Classify each finding as **confirmed** (reproducible from the code), **likely** (strong evidence, not fully proven), or **unsupported** (speculative, contradicted by the code, or style-only).
- Drop unsupported findings and report only the discarded count.
- Not rubber-stamp: it may disagree with a reviewer.

If a finding cannot be verified without running tests, mark it **likely** rather than **confirmed**.

## 6. Report

Output:

```
## Adversarial review: <scope>
Panel: <reviewers> → adjudicator <model>

### Blocking
- `path:line` — <issue>. Failure: <scenario>. Fix: <change>. [confirmed|likely]

### Suggestions
- ...

### Nits
- ...

### Summary
<2-3 sentences: overall risk, whether it is safe to merge, and what could not be verified.>
```

Rules:

- Report only confirmed and likely findings.
- Omit empty severity groups; omit Nits unless they are real.
- Every finding needs `path:line`, a concrete failure scenario, and a specific fix.
- Do not restate the diff.
- If the change is clean, say so plainly.
- Mention when reviewers disagreed and how the adjudicator resolved it.
- Do not claim tests passed unless they were actually run.

## 7. Ground rules

- Read-only. Never edit, commit, push, or post to GitHub unless explicitly asked.
- Do not run the full quality gate as part of the review unless the user asks. This review is semantic, not a replacement for `php jobtrack.php check`.
- Keep the panel small. More than three reviewers adds noise, not signal.
- If the user asks for a follow-up after fixes, re-run the same panel against the new diff rather than assuming the earlier findings are resolved.
