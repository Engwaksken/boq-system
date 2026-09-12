export const toolPermissions = {
    list_boq_projects: "boq.read", get_boq_project: "boq.read", get_boq_items: "boq.read", search_boq_items: "boq.read",
    calculate_boq_total: "boq.calculate", analyse_boq: "boq.calculate", estimate_material_cost: "boq.calculate", estimate_boq_cost: "boq.calculate", recalculate_boq: "boq.calculate",
    search_materials: "materials.read", get_material: "materials.read", find_material_substitutes: "materials.read",
    get_material_current_price: "prices.read", get_material_price_history: "prices.read", compare_material_prices: "prices.read", search_hardware_prices: "prices.read", get_hardware_price_history: "prices.read", compare_hardware_prices: "prices.read", get_recent_price_changes: "prices.read",
    search_suppliers: "suppliers.read", get_supplier: "suppliers.read", compare_suppliers: "suppliers.read", get_supplier_materials: "suppliers.read", get_supplier_price_history: "suppliers.read", get_best_suppliers: "suppliers.read", get_best_hardware_suppliers: "suppliers.read", recommend_material_supplier: "suppliers.read", find_cheaper_alternatives: "suppliers.read",
    get_project_cost_summary: "reports.read", get_price_variance_report: "reports.read", get_material_cost_report: "reports.read", get_supplier_comparison_report: "reports.read", get_price_trend_report: "reports.read",
    get_imported_boq: "boq.read", get_boq_extraction_status: "boq.read", get_unmatched_boq_items: "boq.read"
};
