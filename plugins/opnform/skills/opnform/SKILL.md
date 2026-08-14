---
name: opnform
description: Create, preview, revise, claim, and manage OpnForm forms through MCP. Use when a user asks to build an online form, inspect or update an OpnForm form, publish or trash a form, or search and export OpnForm submissions.
---

# OpnForm

Use the OpnForm MCP server for OpnForm operations. Start as a guest when the user only needs a new form and an interactive preview. Connect an account only when account or workspace data is required.

## Build and revise a guest draft

1. Read `opnform://schemas/agent-form-definition/v1` and `opnform://reference/form-fields/v1` before generating a definition. Do not guess field names or constraints.
2. Call `validate_form_definition`. Resolve every validation error before creating or saving a draft.
3. Call `create_form_draft` with the validated definition.
4. Keep the returned `draft_token` private. It is a capability secret: never quote it to the user, write it to a file, log it, or send it to another service.
5. Call `preview_form_draft` and present its interactive MCP preview when the host supports it.
6. Apply requested changes with `patch_form_draft`, passing the latest `expected_version`. Validate the changed definition before persistence when needed.
7. If the version conflicts or the current state is uncertain, call `get_form_draft`, reconcile the user's requested changes, validate again, and retry with the new version. Never overwrite blindly.
8. Offer to open the draft in OpnForm. Use the `editor_url` returned by the preview or call `open_form_draft_in_editor`. The handoff URL is reusable until the seven-day guest draft expires; generating another URL does not revoke earlier ones.

The browser editor keeps the guest draft available through an HttpOnly session. The user can preview and edit before signing in. Authentication and workspace selection happen only when the user chooses to save the draft into an OpnForm account.

## Work with an authenticated account

Call `get_account_context` first. If exactly one writable workspace is available, select it without asking. If several are available, call `list_workspaces` and ask the user which one to use. Workspace access is read-only through MCP; do not attempt workspace administration.

- Use form listing and lookup tools to identify the target before changing it.
- `create_form` creates a draft. After creation, ask whether the user wants it published.
- Before `update_form`, fetch the form and pass its current `revision`. On conflict, fetch, reconcile, and retry instead of overwriting newer changes.
- Call `publish_form` only after the user explicitly confirms publication, then pass `confirm: true`.
- Call `trash_form` only after the user explicitly confirms moving the form to trash, then pass `confirm: true`. MCP does not expose form restoration or permanent deletion.
- Premium fields may appear in previews. On save, preserve and explain any `disabled_features` and warnings returned by OpnForm; never imply that MCP bypasses the workspace plan.

## Read submissions

Submission access requires OAuth. Authenticate only when the user's request needs account data.

- Use `list_submissions` to browse, filter, and search. Use `get_submission` for one result.
- Use `get_submission_stats` only for aggregates available in OpnForm's form statistics view.
- Use `export_submissions` only when the user requests an export, then poll `get_submission_export` until it is ready.
- Submission access is read-only. Do not create, update, delete, or restore submissions.
- Return only the submission fields needed for the request, especially when values contain personal or sensitive data.

## Safety and recovery

- Treat draft tokens, editor handoffs, OAuth tokens, and export URLs as secrets.
- If a preview or editor URL expires, call the appropriate tool for a fresh URL; never reconstruct one.
- If authentication is unavailable, continue with guest-safe draft tools when the task permits it.
- Never bypass confirmation, workspace permission, validation, revision checks, plan enforcement, or rate limits.
