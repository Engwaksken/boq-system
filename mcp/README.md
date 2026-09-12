# BOQ MCP Server

This stdio server is an API client only. It never connects to MySQL, reads `.env`, executes SQL, or exposes write/delete tools.

## Setup

1. Create a dedicated, active Laravel service-account user in the target organisation.
2. Create a token with only the required abilities:

```powershell
php artisan mcp:token service-account@example.test --abilities=boq.read --abilities=boq.calculate --abilities=materials.read --abilities=prices.read --abilities=suppliers.read --abilities=reports.read
```

3. Set `BOQ_API_URL` to the Laravel `/api/v1` URL and `BOQ_MCP_TOKEN` to the generated token in the OpenCode process environment. Do not add either value to Git or `opencode.json`.
4. Build and verify:

```powershell
cd mcp
npm install
npm test
cd ..
opencode mcp list
```

Without the two environment variables, OpenCode can discover the tools but each call returns the structured `MCP_NOT_CONFIGURED` error.
