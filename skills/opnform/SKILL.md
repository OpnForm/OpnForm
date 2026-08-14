---
name: opnform
description: Create, preview, revise, claim, and manage OpnForm forms through MCP. Use when a user asks to build an online form, inspect or update an OpnForm form, publish or trash a form, or search and export OpnForm submissions.
---

# OpnForm

Use the OpnForm MCP server for every OpnForm operation. Prefer the guest workflow when the user only wants to create and preview a new form; authentication can wait until account data is actually needed.

## Create a form as a guest

1. Read `opnform://schemas/agent-form-definition/v1` and `opnform://reference/form-fields/v1` before composing a definition.
2. Call `validate_form_definition`. Resolve every validation error before persistence.
3. Call `create_form_draft` with the validated definition.
4. Keep the returned `draft_token` private. It is a capability secret and must never be quoted back to the user, written to a file, or sent to another service.
5. Call `preview_form_draft` and show its interactive preview when the host supports MCP Apps.
6. Apply requested revisions with `patch_form_draft`, always passing the latest `expected_version`. If the version is stale, fetch the draft again and reconcile deliberately.
7. Ask whether the user wants to open the draft in OpnForm. Use the short-lived `editor_url` returned by preview, or call `open_form_draft_in_editor` for a fresh one-time link.

The browser editor keeps the draft available through an HttpOnly session. A user may edit it before signup. Claiming or saving into an account happens only after authentication and workspace selection.

## Work with an authenticated account

Call `get_account_context` first. If there is exactly one writable workspace, tools may select it automatically. If there are several, call `list_workspaces` and ask the user which one to use. Workspaces are read-only through MCP.

- `create_form` always creates a draft. After creation, ask whether it should be published.
- Before `update_form`, fetch the form and pass its current `revision`. On a conflict, fetch again and merge instead of overwriting.
- Call `publish_form` only after explicit confirmation. Pass `confirm: true`.
- Call `trash_form` only after explicit confirmation. Pass `confirm: true`. MCP does not restore or permanently delete forms.
- Preserve any `disabled_features` and save warnings returned by OpnForm. Premium-only fields may render in preview but can be disabled when saved under the selected workspace plan.

## Read submissions

Submission access requires OAuth. Use it only when the request needs account data.

- Use `list_submissions` for pagination, filters, and search; use `get_submission` for one record.
- Use `get_submission_stats` for the same aggregate scope as the OpnForm form statistics view.
- Use `export_submissions` only when the user requests an export. Poll `get_submission_export` until it is ready.
- Never infer write access: submission creation, update, deletion, or restoration are not MCP capabilities.
- Do not reproduce sensitive submission values unless they are necessary to answer the user's request.

## Failure handling

- Treat draft tokens, editor handoffs, OAuth tokens, and export URLs as secrets.
- If a preview or editor link expires, request a fresh one; do not attempt to reconstruct it.
- If authentication is unavailable, continue with guest-safe draft tools rather than blocking form creation.
- Never bypass a confirmation, workspace permission, optimistic-lock conflict, validation error, or rate limit.
