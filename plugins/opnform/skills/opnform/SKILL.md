---
name: opnform
description: Create, preview, revise, save, and manage OpnForm forms through MCP, or search, analyze, and export their submissions.
---

# OpnForm

Use the OpnForm MCP tools exposed by the current conversation. Keep replies concise and outcome-first.

## Route the request

- A natural request to create a form starts as a temporary guest draft. Do not ask for login, account, workspace, guest-mode, or preview confirmation.
- Use OAuth only when the user asks to save to an account, manage existing forms, choose account data, or read submissions.
- If OpnForm tools are missing, explain that the plugin is not attached and ask the user to start a new conversation with OpnForm selected. Do not use shell, raw HTTP, another connector, or a recursive agent as a fallback.
- Before an account or submission operation, read [references/account-and-submissions.md](references/account-and-submissions.md).
- For connection failures or host-specific OAuth recovery, read [references/connection-and-recovery.md](references/connection-and-recovery.md).

## Create or revise a guest draft

Success means the latest draft is valid and the turn contains exactly one final interactive preview.

1. Read [references/form-authoring.md](references/form-authoring.md), then read `opnform://schemas/agent-form-definition/v1` and `opnform://reference/form-fields/v1` before generating or materially changing fields, layout, presentation, or media.
2. Call `validate_form_definition`. Correct every validation error, every entry in `quality_warnings` marked `blocking`, and other relevant warnings while preserving intentional user choices.
3. Call `create_form_draft`. It is data-only and not idempotent: after success, keep its `draft_handle` private and do not call it again.
4. Call `preview_form_draft` exactly once with that handle. Do not finish a creation turn with text alone.
5. For a requested revision, use `patch_form_draft` with the latest `expected_version`, then call `preview_form_draft` exactly once. Do not call the preview tool before the mutation or more than once after success.
6. On a version conflict, call `get_form_draft`, reconcile the requested change, and retry with the current version. On a validation error, correct the operation and retry once. Never overwrite blindly.

After the preview, summarize the result or change in one sentence and ask one immediate question: whether the user wants another change or wants to save the form to their OpnForm account. If the user already supplied the next instruction, perform it instead of repeating the question.

Use `preview_form_draft` without a mutation only when the user asks to refresh or redisplay an existing preview. A preview is read-only and remains valid until its seven-day guest draft expires.

Call `open_form_draft_in_editor` only when the user explicitly asks to edit in OpnForm or uses the preview's **Edit in OpnForm** action. Editor links are reusable until the guest draft expires.

## Save and publish

Saving requires account persistence. Connect only at that point, preserve the latest validated definition, and follow the workspace rules in the account reference.

- An account form is first saved as an unpublished draft. Say so, then ask whether the user wants to publish it.
- Asking is not confirmation. Call `publish_form` only after an explicit affirmative response with `confirm_publish: true` and the current revision.
- Require explicit confirmation before `trash_form` with `confirm_trash: true`.
- Explain any `disabled_features` returned when plan rules disable previewed features during save.

## Safety

- Keep `draft_handle`, editor handoffs, OAuth tokens, and export URLs out of user-facing text and logs.
- Never bypass validation, version or revision checks, workspace permissions, confirmations, plan enforcement, or rate limits.
- Submission access is read-only. Never create, update, delete, or restore submissions.
- When a preview fails, report the returned error accurately. A `429` is temporary rate limiting, not an expired draft.
