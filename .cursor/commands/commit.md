# Commit Quality Gate

## Goal

Run all required quality checks before creating a commit:

- back-end tests
- front-end tests
- back-end linters
- front-end linters
- fix issues found during those checks
- generate a commit message proposal based on the git diff

## Steps

1. Run checks in this order:
    - `cd api && ./vendor/bin/pest -p`
    - `cd client && npm run test --if-present`
    - `cd api && ./vendor/bin/pint --test`
    - `cd client && npm run lint`

2. If a command fails:
    - Identify the root cause from the error output.
    - Apply a focused code fix.
    - Re-run only the failing command until it passes.
    - After each fix, re-run all previous checks in the sequence to ensure no regressions.
    - Continue until all checks pass, or clearly report any blocker that cannot be fixed safely.

3. After all checks pass, prepare commit context:
    - `git status`
    - `git diff --stat`
    - `git diff`
    - `git diff --cached`
    - `git log -5 --oneline`

4. Generate a commit message proposal from the diff:
    - 1 short subject line.
    - 1 concise body paragraph focused on why.
    - A compact bullet list of key changed areas/files.
    - Include a short diff summary (main additions/removals and notable files touched).

## Output Format

- Check results in order (pass/fail per command).
- For each failure:
    - failing command
    - root cause
    - fix applied
    - re-check result
- Final section:
    - "Ready to commit" status
    - proposed commit message (subject + body)
    - short diff summary

## Constraints

- Do not commit automatically.
- Make only minimal, relevant fixes required to pass checks.
