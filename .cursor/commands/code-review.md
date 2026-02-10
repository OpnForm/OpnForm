# Code Review Checklist

## Overview

Comprehensive checklist for conducting thorough code reviews to ensure quality, security, and maintainability. Act as a very experienced senior software engineer giving clear and concise feedback. Explain what needs to be done before approving this change.

## Getting the diff to review

- **Review an existing GitHub PR** (e.g. "review PR 1007" or "review #1007"):
  - Fetch the PR diff: `gh pr diff <PR_NUMBER>`
  - Optional context: `gh pr view <PR_NUMBER>` (title, description, base/head refs)
  - To review the PR against a different base branch than the PR’s default (e.g. `main-v2`): `gh pr diff <PR_NUMBER> --base main-v2`
- **Review current branch against a base branch** (e.g. against `main` or `main-v2`):
  - `git diff main-v2...HEAD` (or `git diff main-v2`) to get the diff to review
- **PR already attached**: If the user attached a PR (e.g. from Cursor’s PR attachment), use the provided diff/summary and skip fetching.

Use the resulting diff as the scope of the review. Then apply the checklist below.

## Review Categories

### Functionality

- [ ] Code does what it's supposed to do
- [ ] Edge cases are handled
- [ ] Error handling is appropriate
- [ ] No obvious bugs or logic errors

### Code Quality

- [ ] Code is readable and well-structured
- [ ] Functions are small and focused
- [ ] Variable names are descriptive
- [ ] No code duplication
- [ ] Follows project conventions

### Security

- [ ] No obvious security vulnerabilities
- [ ] Input validation is present
- [ ] Sensitive data is handled properly
- [ ] No hardcoded secrets
