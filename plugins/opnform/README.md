# OpnForm agent plugin

This directory is the installable OpnForm plugin package. It contains both the portable Agent Plugins 1.0 manifests and the native OpenAI wrapper.

## Package formats

- `plugin.json`, `mcp.json`, and `skills/opnform/SKILL.md` are the portable [agent-plugins.org](https://agent-plugins.org) package.
- `.codex-plugin/plugin.json`, `.app.json`, and `.mcp.json` connect the native OpenAI plugin to the registered ChatGPT app and let Codex load the hosted MCP server directly.

Both integrations use `https://api.opnform.com/mcp`. They contain no credentials or environment-specific endpoint.

## Registered ChatGPT app

The production MCP connection is registered in ChatGPT Developer Mode as `OpnForm MCP`. The real app ID is stored in `.app.json`; do not replace it with a placeholder or an ID from a local development connection.

Public directory submission still uses the production MCP URL directly. The registered app mapping is the compatibility wrapper used by the installable ChatGPT/Codex package.

## Local installation

Use a personal or temporary marketplace outside this repository. A local marketplace entry should point to a copy of this directory, then install `opnform` from that marketplace and test it in a new conversation. Do not commit a repository marketplace file.
