<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Mcp\McpException;
use App\Services\Mcp\McpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class McpToolController extends Controller
{
    private const TOOLS = [
        'list_boq_projects' => 'boq.read', 'get_boq_project' => 'boq.read', 'get_boq_items' => 'boq.read', 'search_boq_items' => 'boq.read', 'analyse_boq' => 'boq.calculate', 'calculate_boq_total' => 'boq.calculate', 'estimate_boq_cost' => 'boq.calculate', 'recalculate_boq' => 'boq.calculate',
        'search_materials' => 'materials.read', 'get_material' => 'materials.read', 'get_material_current_price' => 'prices.read', 'get_material_price_history' => 'prices.read', 'compare_material_prices' => 'prices.read', 'search_hardware_prices' => 'prices.read', 'get_hardware_price_history' => 'prices.read', 'compare_hardware_prices' => 'prices.read', 'get_best_hardware_suppliers' => 'suppliers.read', 'get_recent_price_changes' => 'prices.read',
        'search_suppliers' => 'suppliers.read', 'get_supplier' => 'suppliers.read', 'compare_suppliers' => 'suppliers.read', 'get_supplier_materials' => 'suppliers.read', 'get_supplier_price_history' => 'suppliers.read', 'get_best_suppliers' => 'suppliers.read', 'estimate_material_cost' => 'boq.calculate', 'recommend_material_supplier' => 'suppliers.read', 'find_cheaper_alternatives' => 'suppliers.read', 'find_material_substitutes' => 'materials.read',
        'get_project_cost_summary' => 'reports.read', 'get_price_variance_report' => 'reports.read', 'get_material_cost_report' => 'reports.read', 'get_supplier_comparison_report' => 'reports.read', 'get_price_trend_report' => 'reports.read', 'get_imported_boq' => 'boq.read', 'get_boq_extraction_status' => 'boq.read', 'get_unmatched_boq_items' => 'boq.read',
    ];

    public function __invoke(Request $request, string $tool, McpService $service): JsonResponse
    {
        $started = microtime(true); $status = 'success'; $parameters = $request->input('parameters', []);
        try {
            $ability = self::TOOLS[$tool] ?? null;
            if (! $ability || ! $request->user()->currentAccessToken() || ! $request->user()->tokenCan($ability)) throw new McpException('MCP_PERMISSION_DENIED', 'This service credential is not permitted to use this MCP tool.', 403);
            $data = $service->execute($tool, is_array($parameters) ? $parameters : [], $request->user());
            return response()->json(['success' => true, 'data' => $data]);
        } catch (McpException $exception) {
            $status = 'error'; return response()->json(['success' => false, 'code' => $exception->mcpCode, 'message' => $exception->getMessage()], $exception->status);
        } catch (\Throwable $exception) {
            $status = 'error'; Log::error('MCP tool failure', ['tool' => $tool, 'exception' => $exception]);
            return response()->json(['success' => false, 'code' => 'MCP_REQUEST_FAILED', 'message' => 'The BOQ service could not complete this request.'], 500);
        } finally {
            AuditLog::create(['user_id' => $request->user()->id, 'organisation_id' => $request->user()->organisation_id, 'action' => 'mcp.'.$tool, 'entity_type' => 'mcp_tool', 'entity_id' => $parameters['project_id'] ?? null, 'new_value' => ['model' => $request->header('X-MCP-Model', 'unknown'), 'parameters' => $this->safeParameters(is_array($parameters) ? $parameters : []), 'status' => $status, 'execution_duration_ms' => (int) ((microtime(true) - $started) * 1000)], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'reference' => 'BOQ MCP']);
        }
    }

    private function safeParameters(array $parameters): array { unset($parameters['token'], $parameters['authorization'], $parameters['password'], $parameters['api_key']); return $parameters; }
}
