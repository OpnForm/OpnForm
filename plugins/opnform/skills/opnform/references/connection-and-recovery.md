# Connection and recovery

Distinguish missing tools from missing authentication.

- Missing OpnForm tools means the plugin is not attached to the conversation. Ask the user to start a new conversation with OpnForm selected before the first message.
- An OAuth challenge from an account-scoped tool means the MCP server is available but the account is not connected. Ask the user to complete the host's connection flow.
- Enabling or selecting the plugin is not OAuth authentication.
- In local Codex, the user can authenticate through **Settings → MCP servers → OpnForm → Authenticate** or run `codex mcp login opnform`. A new conversation may be required afterward because existing MCP clients do not always hot-reload credentials.
- Do not loop on an OAuth challenge or claim that connection succeeded without a successful account-scoped result.
- If a guest-safe request can continue without authentication, keep using guest tools.
- If a preview URL fails while the guest draft is active, call `preview_form_draft` for a fresh render, passing the displayed `version` when known so recovery does not substitute the latest content. If the seven-day draft itself expired, explain that it must be recreated. Saving through the browser editor claims the guest draft: only existing signed snapshots remain readable until expiry, and guest tools cannot generate new previews or restore it. Saving through `create_form_in_account` copies the definition without claiming the guest draft, so guest preview and restoration remain available until expiry. Guest restoration never changes the independent saved account form.
