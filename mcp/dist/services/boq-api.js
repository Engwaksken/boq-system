export class BoqApi {
    baseUrl;
    token;
    model;
    timeoutMs;
    constructor(baseUrl, token, model, timeoutMs) {
        this.baseUrl = baseUrl;
        this.token = token;
        this.model = model;
        this.timeoutMs = timeoutMs;
    }
    async execute(tool, parameters) {
        if (!this.baseUrl || !this.token) {
            return { success: false, code: "MCP_NOT_CONFIGURED", message: "The BOQ MCP service credential has not been configured." };
        }
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), this.timeoutMs);
        try {
            const response = await fetch(`${this.baseUrl.replace(/\/$/, "")}/mcp/tools/${encodeURIComponent(tool)}`, {
                method: "POST",
                headers: { "Content-Type": "application/json", Authorization: `Bearer ${this.token}`, "X-MCP-Model": this.model },
                body: JSON.stringify({ parameters }),
                signal: controller.signal
            });
            return await response.json();
        }
        catch (error) {
            return { success: false, code: "MCP_API_UNAVAILABLE", message: error instanceof Error && error.name === "AbortError" ? "The BOQ API request timed out." : "The BOQ API could not be reached." };
        }
        finally {
            clearTimeout(timeout);
        }
    }
}
