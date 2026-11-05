# MCP OAuth discovery

The hosted API image runs Nginx on port 8080 and forwards PHP requests to
PHP-FPM on port 9000 inside the same container.

The API Nginx configuration routes these three public metadata URLs to Laravel:

- `/.well-known/oauth-authorization-server`
- `/.well-known/oauth-protected-resource`
- `/.well-known/oauth-protected-resource/mcp`

These exact locations take precedence over the dotfile deny rule. Keep that rule
for all other hidden paths, including `.env` and `.git/config`. Do not expose the
whole `.well-known` directory to fix OAuth discovery.

After deploying with OAuth enabled, verify that the three metadata URLs return
HTTP 200 with valid JSON, that `.env` and `.git/config` still return HTTP 403,
and that `/healthz` remains healthy. Then test a fresh MCP OAuth connection;
metadata availability alone does not prove sign-in or token exchange succeeds.
