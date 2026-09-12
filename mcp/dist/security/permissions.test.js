import assert from "node:assert/strict";
import test from "node:test";
import { toolPermissions } from "./permissions.js";
test("only approved read and calculation MCP abilities are exposed", () => {
    const allowed = new Set(["boq.read", "boq.calculate", "materials.read", "prices.read", "suppliers.read", "reports.read"]);
    assert.ok(Object.values(toolPermissions).every((permission) => allowed.has(permission)));
    assert.equal(toolPermissions.delete_project, undefined);
    assert.equal(toolPermissions.run_sql, undefined);
});
