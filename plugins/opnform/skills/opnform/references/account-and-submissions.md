# Account forms and submissions

Authenticated tools remain discoverable before OAuth. Request authentication only when this workflow actually needs account data.

## Workspaces and forms

- Call `get_account_context` when the user asks to save or manage account data.
- If exactly one writable workspace is available, use it without asking. If several are available, call `list_workspaces` and ask which one to use. Workspace management is not exposed.
- `create_form_in_account` saves an unpublished draft. After success, state that it is saved but unpublished and ask whether to publish.
- Before `update_form`, `publish_form`, or `trash_form`, fetch the form and pass its current `revision` as `expected_revision`.
- On conflict, fetch, reconcile, and retry. Never overwrite newer work blindly.
- Publish only after explicit confirmation using `confirm_publish: true`.
- Move a form to trash only after explicit confirmation using `confirm_trash: true`. Restore and permanent deletion are not exposed.
- Premium fields may appear in guest previews. Preserve and explain `disabled_features` and warnings returned during save.

## Submissions

- Use `list_submissions` to browse, filter, and search response values. Use `get_submission` for one result.
- Use `get_submission_stats` only for aggregates available in OpnForm's form statistics view.
- Use `export_submissions` only when the user requests an export, then poll `get_submission_export` until ready.
- Submission access is read-only. Do not create, update, delete, or restore submissions.
- Return only the fields needed for the request, especially when submissions contain personal or sensitive data.
