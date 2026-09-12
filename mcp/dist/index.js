import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import { BoqApi } from "./services/boq-api.js";
import { toolPermissions } from "./security/permissions.js";
import { boqTools } from "./tools/boq.js";
import { materialTools } from "./tools/materials.js";
import { priceTools } from "./tools/prices.js";
import { supplierTools } from "./tools/suppliers.js";
import { reportTools } from "./tools/reports.js";
import { projectTools } from "./tools/projects.js";
const apiUrl = process.env.BOQ_API_URL;
const token = process.env.BOQ_MCP_TOKEN;
const api = new BoqApi(apiUrl ?? "", token ?? "", process.env.BOQ_MCP_MODEL ?? "opencode", Number(process.env.BOQ_MCP_TIMEOUT_MS ?? 30000));
const server = new McpServer({ name: "boq", version: "1.0.0" });
const schema = {
    project_id: z.number().int().positive().optional(), boq_id: z.number().int().positive().optional(), material_id: z.number().int().positive().optional(), hardware_price_id: z.number().int().positive().optional(),
    query: z.string().max(200).optional(), material: z.string().max(200).optional(), supplier: z.string().max(200).optional(), category: z.string().max(100).optional(), location: z.string().max(100).optional(), currency: z.string().length(3).optional(),
    quantity: z.number().positive().optional(), days: z.number().int().min(1).max(365).optional(), minimum_change: z.number().min(0).optional(), per_page: z.number().int().min(1).max(100).optional()
};
const tools = [...boqTools, ...materialTools, ...priceTools, ...supplierTools, ...reportTools, ...projectTools];
for (const tool of tools) {
    server.tool(tool, `BOQ ${tool.replaceAll("_", " ")} (${toolPermissions[tool]}). Returns verified application data only.`, schema, async (parameters) => {
        const result = await api.execute(tool, parameters);
        return result.success
            ? { content: [{ type: "text", text: JSON.stringify(result.data) }] }
            : { content: [{ type: "text", text: JSON.stringify({ success: false, code: result.code, message: result.message }) }], isError: true };
    });
}
await server.connect(new StdioServerTransport());
