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

For creation or edits, success means the latest draft is valid and the turn contains exactly one final interactive preview. For historical comparison, show the requested version without changing the current draft.

1. Read [references/form-authoring.md](references/form-authoring.md), then read `opnform://schemas/agent-form-definition/v1` and `opnform://reference/form-fields/v1` before generating or materially changing fields, layout, presentation, or media.
2. Call `validate_form_definition`. Correct every validation error, every entry in `quality_warnings` marked `blocking`, and other relevant warnings while preserving intentional user choices.
3. Call `create_form_draft`. It is data-only and not idempotent: after success, keep its `draft_handle` private and do not call it again.
4. Call `preview_form_draft` exactly once with that handle. Do not finish a creation turn with text alone.
5. For a requested content or layout change, use `patch_form_draft` with the latest `expected_version`, then call `preview_form_draft` exactly once. Do not call the preview tool before the mutation or more than once after success.
6. On a version conflict, call `get_form_draft`, reconcile the requested change, and retry with the current version. On a validation error, correct the operation and retry once. Never overwrite blindly.

Required final reply after each successful preview: summarize the result or change in one sentence, then ask exactly one question offering two choices—make another change or save the form to the user's OpnForm account. A status-only response is incomplete. If the user already supplied the next instruction, perform it instead of repeating the question.

To compare an earlier version, pass its `version` to `get_form_draft` or `preview_form_draft`. Omit `version` to fetch the current draft. Comparing history is read-only; do not patch or restore it.

When the user asks to return to an earlier version, fetch the latest draft without `version`, then call `restore_form_draft_version` with that latest `expected_version` and the historical `version`. Restoration creates a new current version and preserves intervening revisions. After success, call `preview_form_draft` exactly once. Do not reconstruct the old definition through patch operations or retry a successful restoration.

Use `preview_form_draft` without a mutation when the user asks to refresh, redisplay, or compare an existing version. Each preview stays pinned until the seven-day guest draft expires. Saving via the browser editor claims the draft: guest editing and restoration stop, but existing signed, versioned previews remain readable until expiry. `create_form_in_account` creates an independent copy and leaves the guest draft active until expiry. Guest edits or restoration never update that account form; use account tools for saved-form changes.

Call `open_form_draft_in_editor` only when the user explicitly asks to edit in OpnForm or uses the preview's **Edit in OpnForm** action. Pass the displayed version as `expected_version`. A pinned editor link can be reused while that version remains current and the draft has not expired. If it becomes stale, open the latest preview; restore an older version only when the user asks to return to it.

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
