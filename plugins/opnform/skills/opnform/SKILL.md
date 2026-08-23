---
name: opnform
description: Use this when a user asks to create, preview, revise, claim, or manage an OpnForm form through MCP, or to search, inspect, analyze, or export OpnForm submissions.
---

# OpnForm

Use the OpnForm MCP server for OpnForm operations. Start as a guest when the user only needs a new form and an interactive preview. Connect an account only when account or workspace data is required.

Treat an unqualified request such as “create a contact form”, “build a survey”, or “make me a registration form” as a guest draft request. Do not infer that the user wants account persistence merely because the plugin offers OAuth or the host displays a Connect button. Do not ask the user to sign in, call `get_account_context`, or call `create_form_in_account` unless the user explicitly asks to save the new form in their OpnForm account or a workspace.

## Require native MCP access

- Use only the OpnForm MCP tools and resources exposed by the current host session.
- Never launch another Codex or ChatGPT agent, run `codex exec`, call OpnForm through shell commands or raw HTTP, inspect a repository or plugin cache for live product data, or switch to another OpnForm connector as a fallback.
- If the required OpnForm tools or resources are absent, stop. Explain that OpnForm is not attached to the current conversation and ask the user to start a new conversation with the OpnForm plugin selected before sending the first message. Do not attempt login, installation, or discovery through another route.
- Distinguish missing tools from missing authentication. Missing tools require a correctly attached plugin; an OAuth challenge from an available account tool means the OpnForm MCP server itself is not authenticated.
- Enabling or selecting the plugin is not OAuth authentication. In local Codex, tell the user to open **Settings → MCP servers → OpnForm → Authenticate** (or run `codex mcp login opnform` themselves). After OAuth completes, start a new conversation with OpnForm selected before the first message so Codex creates the MCP client with the stored credential. Existing local conversations can remain attached to their original guest client and may not hot-reload OAuth credentials. In hosted ChatGPT, use the connection flow presented by the host and retry in the same conversation only if the host supports it.
- If the tool still returns the OAuth challenge afterward, do not loop or claim the connection succeeded. In local Codex, first verify the OpnForm MCP server is authenticated, then move the request to a new conversation. If a fresh conversation still challenges, explain that no bearer credential reached the MCP server and ask the user to verify the server's authentication status.

## Build and revise a guest draft

This is the default workflow for new-form requests, including when the user does not mention “guest”, “draft”, “preview”, or “without login”. The presence of OAuth-protected account tools does not make authentication a prerequisite for these steps.

1. Read `opnform://schemas/agent-form-definition/v1` and `opnform://reference/form-fields/v1` before generating a definition. Follow the field reference's `authoring_guidelines`. Re-read the field reference before changing presentation style, fields, layout, or media later in the conversation. Do not inspect the OpnForm source tree or guess product behavior when the MCP resources describe it.
2. Perform the respondent-facing quality pass below, then call `validate_form_definition`. Resolve every validation error. Treat `quality_warnings` as non-blocking editorial guidance: correct relevant warnings before creating or saving while preserving intentional user choices.
3. Call `create_form_draft` with the validated definition.
4. Keep the returned `draft_handle` in tool context and pass it unchanged to guest draft tools. It is an internal opaque reference, not user-facing form content: do not quote it to the user, write it to a file, log it, or send it to another service.
5. Call `preview_form_draft` and present its interactive MCP preview when the host supports it. This is a read-only step and must not create an editor link.
6. Apply requested changes with `patch_form_draft`, passing the latest `expected_version`. Validate the changed definition before persistence when needed.
7. If the version conflicts or the current state is uncertain, call `get_form_draft`, reconcile the user's requested changes, validate again, and retry with the new version. Never overwrite blindly.
8. If a patch returns a validation error, correct the invalid operation and retry once with the same latest version. Report the returned validation problem accurately; do not turn a recoverable enum or field error into a generic “server error”.
9. Offer to open the draft in OpnForm. Call `open_form_draft_in_editor` only after the user chooses the preview's **Open in OpnForm** action or explicitly asks to continue in the editor. The returned handoff URL is reusable until the seven-day guest draft expires; generating another URL does not revoke earlier ones.

The browser editor keeps the guest draft available through an HttpOnly session. The user can preview and edit before signing in. Authentication and workspace selection happen only when the user chooses to save the draft into an OpnForm account.

### Produce a polished first draft

- Infer the form's purpose, audience, language, and tone. Make conservative assumptions and show a useful first draft instead of blocking on non-essential questions.
- Keep short forms short. Unless the form is intentionally trivial, begin with a concise `nf-text` heading and one supporting sentence explaining its purpose.
- Use human-facing labels in sentence case. Use short noun labels for identity fields and direct, unbiased questions for surveys; never expose raw identifiers or snake_case.
- Add placeholders only when they provide a useful example or expected format. Never use them instead of visible labels or repeat “Enter your [label]”.
- Add help text only for constraints, unfamiliar information, formatting, or why information is requested.
- Use the most specific field type and mark only essential fields as required. Avoid duplicate or unnecessary questions.
- In classic mode, pair at most two related short fields on a row and keep long answers full width. Split long forms by meaningful sections, not arbitrary field counts.
- Use clean neutral style defaults unless the user supplies brand direction. Do not invent brand colors, logos, decorative media, legal consent, sensitive questions, response times, or business commitments.
- Use a contextual action such as “Send message”, “Request a quote”, or “Join the waitlist”, not a generic “Submit”. Add a specific completion message that confirms what happened and, when known, what comes next.
- Keep language, capitalization, tone, terminology, and option style consistent throughout the form.

### Common field mappings

- Use `type: text` for both short text and long-answer fields. Set `multi_lines: true` for a message, comment, description, or other textarea-style answer; `textarea` is not a valid OpnForm field type.
- Use `type: phone_number` for phone inputs, `type: email` for email inputs, and `type: url` for website inputs.
- Use `type: select` for one choice and `type: multi_select` for multiple choices. Build their option objects exactly as documented by `opnform://reference/form-fields/v1`.
- If a requested field does not map cleanly, re-read the field reference and validate the whole definition. Never guess a field type and never continue to draft creation after validation fails.

## Presentation and media

- `classic` is a continuous form. Use widths for columns and `nf-page-break` only when the user wants explicit pagination.
- `focused` is a Typeform-like flow: every visible block becomes one step automatically. Use full-width input blocks and concise `nf-text` intro steps. Remove `nf-page-break` and do not add standalone `nf-image`, `nf-divider`, `nf-code`, `nf-video`, or `nf-audio` blocks.
- Use the exact documented style values. In particular: `border_radius` is `none`, `small`, or `full`; `width` is `centered` or `full`; `size` is `sm`, `md`, or `lg`; and `theme` is `default`, `simple`, `notion`, `minimal`, or `transparent`. Never invent aliases such as `medium` or `large`.
- To illustrate a focused step, add an `image` object to that input or `nf-text` block. Follow the layouts and media schema in `opnform://reference/form-fields/v1`; do not confuse this with the standalone `nf-image.image_block` shape.
- Use only user-provided or durable public HTTPS image URLs. Never persist localhost, private addresses, temporary tunnel domains, expiring signed URLs, or asset paths found by reading the OpnForm repository.
- Preserve the existing language, copy, field IDs, and unrelated settings unless the user asks to change them. For a focused conversion, usually set `settings.auto_next`, `settings.navigation_arrows`, and the localized `translations.focused_next_button_text` deliberately.

## Work with an authenticated account

Authenticated tools remain discoverable before the account is connected. When the user asks for account, form, or submission data, call `get_account_context`; if the host returns an OAuth challenge, ask the user to complete the connection. In local Codex, continue from a new conversation after OAuth instead of repeatedly retrying from the guest conversation. Do not report that account tools are unavailable merely because the current session started as a guest.

For new forms, enter this authenticated workflow only when the user explicitly asks to save directly to their account or names an account/workspace destination. A request to create, build, or preview a form by itself remains in the guest workflow even when an account connection is available.

After authentication, use the account context to choose a workspace. If exactly one writable workspace is available, select it without asking. If several are available, call `list_workspaces` and ask the user which one to use. Workspace access is read-only through MCP; do not attempt workspace administration.

- Use form listing and lookup tools to identify the target before changing it.
- `create_form_in_account` creates an account-owned draft. After creation, ask whether the user wants it published.
- Before `update_form`, `publish_form`, or `trash_form`, fetch the form and pass its current `revision` as `expected_revision`. On conflict, fetch, reconcile, and retry instead of overwriting newer changes.
- Call `publish_form` only after the user explicitly confirms publication, then pass the confirmed form's `expected_revision` and `confirm_publish: true`.
- Call `trash_form` only after the user explicitly confirms moving the form to trash, then pass the confirmed form's `expected_revision` and `confirm_trash: true`. MCP does not expose form restoration or permanent deletion.
- Premium fields may appear in previews. On save, preserve and explain any `disabled_features` and warnings returned by OpnForm; never imply that MCP bypasses the workspace plan.

## Read submissions

Submission access requires OAuth. Authenticate only when the user's request needs account data.

- Use `list_submissions` to browse, filter, and search. Use `get_submission` for one result.
- Use `get_submission_stats` only for aggregates available in OpnForm's form statistics view.
- Use `export_submissions` only when the user requests an export, then poll `get_submission_export` until it is ready.
- Submission access is read-only. Do not create, update, delete, or restore submissions.
- Return only the submission fields needed for the request, especially when values contain personal or sensitive data.

## Safety and recovery

- Keep internal draft handles out of user-facing text and logs. Treat editor handoffs, OAuth tokens, and export URLs as sensitive credentials.
- If a preview or editor URL expires, call the appropriate tool for a fresh URL; never reconstruct one.
- If authentication is unavailable, continue with guest-safe draft tools when the task permits it.
- Never bypass confirmation, workspace permission, validation, revision checks, plan enforcement, or rate limits.
