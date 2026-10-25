# Security remediation deployment notes — October 2026

Run the database migrations before deploying the API and client together. Older clients cannot poll new AI completions without the capability token. Use a shared cache backend supporting atomic locks across workers.

## API and embedding contracts

- AI generation returns `ai_form_completion_id` and `ai_form_completion_token`. Poll with `X-AI-Completion-Token`; the client keeps the token in session storage. A shared IP address is no longer authorization. Old completions without a token cannot be read publicly.
- `forms.share_submission_data_with_parent` defaults to false for existing and new forms. The parent iframe receives the submission notification without answers or attribution. Enable **Share answers with the embedding page** in Security & Access only when the host page should receive those values. Existing scripts reading `submission_data` must opt in; arbitrary embedding pages can receive answers when this option is enabled.
- S3 presigning only accepts the configured bucket. Anonymous respondent uploads remain supported. Configure bucket/IAM/quota policy separately.
- Configure all SMTP fields together, including a valid sender address and port between 1 and 65535.
- The `generate` command validates TLS. Production client sourcemaps are disabled without a Sentry upload token. With a token, maps are hidden and deleted from client output after upload. Purge old publicly deployed maps during release.

## Validation boundaries

These changes are validated locally. They do not change CDN, DNS, HSTS, provider account access, or production configuration. Apply the migrations to a staging database and exercise the real provider flows before release. See the local confidential triage report for finding-by-finding decisions and unresolved risks.

## OpnForm changes and compatibility

- Migrations add the AI access-token hash, iframe sharing flag and nullable `users.auth_version`. Password changes revoke previous JWTs, personal tokens and Passport tokens. Tokens created before migration remain valid until the password changes. A password change signs the user out of existing sessions.
- OAuth redirect callbacks require the browser's locally recorded state and the server's provider/user-bound state. A state is consumed before exchanging the code. Restart login after a failed callback; storage-blocked browsers must allow storage for this flow.
- OIDC checks issuer, audience, expiration, authorized party and nonce. OIDC HTTP requests require public HTTPS endpoints, pinned DNS and no redirects. Private-network IdPs and redirecting discovery endpoints are incompatible with this policy and require a separately reviewed configuration before use.
- The Public Suffix List snapshot lives in `api/resources/data/public_suffix_list.dat`; update it alongside the parser dependency when suffix ownership changes. Its upstream license header is preserved.
- Form imports only fetch the allowed host directly, without redirects. Use the final allowed URL rather than a shortening/redirect URL.
- Field-matching updates require every configured match field. Incomplete/empty matches create a new response rather than overwrite an arbitrary response.
- CSV text that starts with a spreadsheet formula marker is prefixed with an apostrophe. Real numeric negative values remain numeric.
- PDF cache keys include form, response and template contents; storage failures are surfaced before success.
- The last workspace administrator cannot be removed, demoted or leave before another administrator is assigned.
- License outage grace is bounded to 24 hours after successful verification. A deleted Stripe subscription ID cannot be reactivated by a delayed update.
- Development auto-login stays available in the managed worktree. Production configuration must not contain those credentials. Authentication pages use `private, no-store`; frame policy preserves other CSP directives.

## Remaining limitations

Submission processing is not fully idempotent across DB/files/queue acknowledgement. Metadata updates in the tax dataset store are not protected by a dataset-wide transaction. Custom-domain scope restoration supports sequential requests, not concurrent coroutines sharing the same model scope registry. Billing event ordering other than terminal cancellation still requires a durable event strategy.

## Compatibility refresh — 25 October 2026

- Keep the current Laravel 12.70.1 / Nuxt 4.6.2 dependency baseline when integrating these changes; use the project-pinned Node 24 runtime.
- Sentry 11 uses top-level upload options. Client sourcemaps remain disabled without an upload token; with a token they are hidden and deleted after upload. A real upload and CDN purge still need release validation.
- Invalid regex conditions evaluate to false for both positive and negative operators in the browser and API. This does not impose a runtime limit on expensive valid patterns.
- Widget emoji values are rendered as text in both source and minified distributions.
- Explicit record defaults precede field prefills and preserve zero and false. URL answers retain priority.
- Preserve main’s strict export form-ID check and its draft-hydration/background-sync sequencing when merging further changes.

## Combined security triage compatibility — 25 October 2026

- New custom domains require the workspace-specific TXT proof shown in settings before saving. Existing saved domains remain usable; retain the CNAME and a stable application key. The proof is bound to the product, workspace and domain. Support impersonation retains the same workspace authorization.
- Password and email changes invalidate old password-reset links. New passwords are limited to 72 bytes before bcrypt hashing; account names reject links and markup on registration and profile updates.
- Password changes also invalidate pending MCP login tickets, existing MCP web consent sessions and unexchanged Passport authorization codes. In-flight login tickets created by an older release must be restarted once. Existing unstamped web sessions remain compatible until the first password rotation, after which a fresh authenticated login ticket is required. Fresh PKCE authorization remains supported.
- Stripe state distinguishes the selected OAuth provider from the resolved connected account. Re-rendering the same payment preserves its card and intent; changing the provider clears them and ignores stale account responses.
- Delayed workspace settings responses update the workspace that was saved, even if the user has selected another workspace in the meantime.
- Form conditions treat zero and false as answered values. Matrix `contains` succeeds when any configured row matches, and empty AND/OR groups evaluate to false. Shared API/browser fixtures cover these contracts, including absent matrix rows and array-valued answers.
- The Linux image CI uses the explicitly permitted `e2e` application environment for its isolated database seed and API. Production seed guards remain unchanged.
