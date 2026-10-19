---
name: review-response
description: Use when the user wants to respond to human code review comments collected via vibediff — triggers on phrases like "start vibediff", "review my pre-commit code", "respond to the review", "respond to review comments", or "address the comments". Two-phase workflow keyed off the .vibediff.pid file.
---

# Review Response

Drive a **two-phase** workflow around `vibediff`, which opens a browser for the
user to leave comments on pre-commit code and writes those comments to
`review-comments.txt` when the process ends.

Determine the phase from the state file `.vibediff.pid` in the repo root:

- Phase 1 (**start**) — user asks to start the review / invoke the skill with no
  pending run.
- Phase 2 (**respond**) — user says to respond to (or address) the review, and a
  `.vibediff.pid` file exists.

Never edit code, commit, or post comments to GitHub outside of acting on the
user's actual review comments.

## Phase 1 — Start the review

1. If `.vibediff.pid` already exists, treat it as a stale run: kill the PID
   (see Phase 2 step 1) and remove the file before starting.
2. Launch vibediff in the background and record its PID. The tool call must
   return immediately; do not block on the browser.

   ```bash
   vibediff > review-comments.txt 2> vibediff.log & echo $! > .vibediff.pid
   ```

3. Confirm to the user that the browser is open and tell them to leave their
   comments, then say **"respond to the review"** when done.

## Phase 2 — Respond to the review

1. **Stop the process.** Read the PID and kill it. If the process already
   exited, continue regardless.

   ```bash
   PID=$(cat .vibediff.pid 2>/dev/null); kill "$PID" 2>/dev/null || taskkill //PID "$PID" //F 2>/dev/null; true
   ```

2. **Read** `review-comments.txt`. If it is empty or missing, tell the user and
   stop (clean up state files first).

3. **Parse the comments.** Format is a header line `<path>:<line>` followed by
   the comment body, which may span multiple lines including blank lines. A new
   header starts the next comment. Example:

   ```
   .opencode/skills/review/SKILL.md:3
   This is a comment.

   It has multiple lines.

   .opencode/skills/review/SKILL.md:33
   This is another comment.
   ```

4. **Act on each comment by intent:**
   - **Change requested** — implement it, then note the `file:line` you
     addressed. Line numbers were captured at review time; locate the relevant
     code by content, not by the raw number.
   - **Question** — answer it in the chat. Do not change code.
   - **Unclear** whether a code change is wanted — **ask the user**; never
     assume.

5. **Clean up.** Delete `review-comments.txt`, `.vibediff.pid`, and
   `vibediff.log`.

   ```bash
   rm -f review-comments.txt .vibediff.pid vibediff.log
   ```

6. **Summarize** — for each comment, state whether it was implemented (with the
   files changed), answered (with the answer), or left for the user to clarify.
