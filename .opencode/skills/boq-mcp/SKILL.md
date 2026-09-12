---
name: boq-mcp
description: Use when analysing BOQ projects, materials, hardware prices, suppliers, calculations, or reports in this application.
---
# BOQ MCP Data Rules

Use the BOQ MCP tools for BOQ business data whenever available. Never fabricate material prices, supplier information, price history, exchange rates, or calculation results.

Prefer verified recent prices, include their source date in conclusions, and use historical-price tools for trend analysis. Report `price_status: unavailable` or missing prices rather than guessing.

Do not modify BOQ records unless the user explicitly requests it. Never expose API keys, database credentials, MCP tokens, or other secrets.
