# OpnForm agent plugin

This directory is the installable OpnForm plugin package. It contains both the portable Agent Plugins 1.0 manifests and the native OpenAI wrapper.

## Package formats

- `plugin.json`, `mcp.json`, and `skills/opnform/SKILL.md` are the portable [agent-plugins.org](https://agent-plugins.org) package.
- `.codex-plugin/plugin.json` and `.mcp.json` let Codex load the hosted OpnForm MCP server directly.

Both integrations use `https://api.opnform.com/mcp`. They contain no credentials or environment-specific endpoint.

## Remaining ChatGPT publication step

ChatGPT public distribution uses a registered MCP connection rather than the direct Codex MCP declaration. Before submitting the plugin to the public directory:

1. Enable Developer mode in ChatGPT.
2. Register `https://api.opnform.com/mcp` from the ChatGPT Plugins page.
3. Copy the technical app ID that ChatGPT creates.
4. Add `.app.json` with that real registered app mapping.
5. Add `"apps": "./.app.json"` to `.codex-plugin/plugin.json`.
6. Re-run the package validators and test the installed plugin in a new conversation.

Do not add `.app.json` before the real registered app ID is available. A fabricated identifier makes the package look publishable while leaving the ChatGPT integration unusable.

## Local installation

Use a personal or temporary marketplace outside this repository. A local marketplace entry should point to a copy of this directory, then install `opnform` from that marketplace and test it in a new conversation. Do not commit a repository marketplace file.
