<?php

// Explicit display-column allowlists: credentials, tokens and private settings
// are never serialized. The source is the page's existing filtered query.
$projects = ['name' => 'Project', 'code' => 'Code', 'status' => 'Status', 'location' => 'Location', 'currency' => 'Currency', 'created_at' => 'Created'];
$boqs = ['name' => 'BOQ', 'reference' => 'Reference', 'project.name' => 'Project', 'status' => 'Status', 'currency' => 'Currency', 'created_at' => 'Created'];
$items = ['item_code' => 'Code', 'description' => 'Description', 'quantity' => 'Quantity', 'unit' => 'Unit', 'original_rate' => 'Original rate', 'ai_suggested_rate' => 'AI rate', 'reviewed_rate' => 'Reviewed rate', 'approved_rate' => 'Approved rate', 'amount' => 'Amount', 'currency' => 'Currency', 'status' => 'Status'];
$plans = ['name' => 'Plan', 'code' => 'Code', 'price' => 'Price', 'currency' => 'Currency', 'duration_days' => 'Duration days', 'duration_hours' => 'Duration hours', 'is_active' => 'Active'];
$subscriptions = ['user.name' => 'User', 'user.email' => 'Email', 'plan.name' => 'Plan', 'plan.price' => 'Price', 'plan.currency' => 'Currency', 'status' => 'Status', 'payment_status' => 'Payment status', 'start_date' => 'Start date', 'end_date' => 'End date', 'created_at' => 'Created'];
$users = ['name' => 'Name', 'email' => 'Email', 'organisation.name' => 'Organisation', 'roles.*.name' => 'Roles', 'email_verified_at' => 'Email verified', 'created_at' => 'Created'];
$prices = ['item_name' => 'Item', 'brand' => 'Brand', 'category' => 'Category', 'supplier' => 'Supplier', 'location' => 'Location', 'unit' => 'Unit', 'price' => 'Price', 'currency' => 'Currency', 'price_type' => 'Type', 'fetched_at' => 'Updated'];
$faqs = ['question' => 'Question', 'answer' => 'Answer', 'is_active' => 'Active', 'sort_order' => 'Sort order'];
$topups = ['name' => 'Name', 'code' => 'Code', 'type' => 'Type', 'price' => 'Price', 'currency' => 'Currency', 'is_active' => 'Active'];

return [
    App\Livewire\Dashboard::class => ['summary' => ['metric' => 'Metric', 'value' => 'Value'], 'projects' => $projects],
    App\Livewire\Projects\Index::class => ['projects' => $projects],
    App\Livewire\Projects\Show::class => ['boqs' => $boqs],
    App\Livewire\Boqs\Index::class => ['boqs' => $boqs],
    App\Livewire\Boqs\Show::class => ['items' => $items],
    App\Livewire\HardwarePrices\Index::class => ['prices' => $prices],
    App\Livewire\HardwarePrices\Compare::class => ['comparison' => $prices],
    App\Livewire\HardwarePrices\Recommendations::class => ['recommendations' => $prices + ['rating.overall' => 'Rating', 'price_history.trend' => 'Trend']],
    App\Livewire\HardwarePrices\Show::class => ['prices' => $prices, 'history' => ['recorded_at' => 'Recorded', 'price' => 'Price', 'currency' => 'Currency', 'supplier' => 'Supplier', 'location' => 'Location']],
    App\Livewire\SupplierRatings\Index::class => ['topHardware' => ['rank' => 'Rank', 'supplier.name' => 'Supplier', 'supplier.location' => 'Location', 'average' => 'Average rating', 'score' => 'Ranking score', 'count' => 'Ratings'], 'topFactories' => ['rank' => 'Rank', 'supplier.name' => 'Factory', 'supplier.location' => 'Location', 'average' => 'Average rating', 'score' => 'Ranking score', 'count' => 'Ratings']],
    App\Livewire\Plans\Index::class => ['plans' => $plans],
    App\Livewire\Subscriptions\Index::class => ['subscriptions' => $subscriptions, 'plans' => $plans],
    App\Livewire\Expenses\Index::class => ['expenses' => ['purchase_date' => 'Date', 'project.name' => 'Project', 'supplier' => 'Supplier', 'description' => 'Description', 'quantity' => 'Quantity', 'unit' => 'Unit', 'rate' => 'Rate', 'total' => 'Total', 'currency' => 'Currency', 'is_planned' => 'Planned', 'explanation' => 'Explanation']],
    App\Livewire\Team\Index::class => ['invitations' => ['email' => 'Email', 'role.name' => 'Role', 'expires_at' => 'Expires', 'accepted_at' => 'Accepted', 'revoked_at' => 'Disabled', 'created_at' => 'Created']],
    App\Livewire\Team\Assignments::class => ['assignments' => ['project.name' => 'Project', 'user.name' => 'Name', 'user.email' => 'Email', 'role' => 'Role', 'created_at' => 'Assigned']],
    App\Livewire\Reports\Accounting::class => ['portfolio' => ['name' => 'Project', 'code' => 'Code', 'status' => 'Status', 'currency' => 'Currency', 'budget' => 'Budget', 'spent' => 'Expenditure', 'balance' => 'Balance', 'progress' => 'Used %']],
    App\Livewire\Faqs::class => ['faqs' => $faqs],
    App\Livewire\System\McpActivity::class => ['logs' => ['created_at' => 'Date', 'user.name' => 'User', 'action' => 'Action', 'reference' => 'Reference']],
    App\Livewire\Topups\Index::class => ['catalog' => $topups, 'purchases' => ['topup.name' => 'Top-up', 'status' => 'Status', 'created_at' => 'Purchased']],
    App\Livewire\Admin\Index::class => ['subscriptions' => $subscriptions, 'plans' => $plans, 'users' => $users],
    App\Livewire\Admin\UsersManager::class => ['users' => $users],
    App\Livewire\Admin\PlansManager::class => ['plans' => $plans],
    App\Livewire\Admin\SubscriptionsManager::class => ['subscriptions' => $subscriptions],
    App\Livewire\Admin\TopupsManager::class => ['topups' => $topups],
    App\Livewire\Admin\VersionsManager::class => ['versions' => ['name' => 'Name', 'version_number' => 'Version', 'release_date' => 'Release date', 'is_active' => 'Active']],
    App\Livewire\Admin\RatesManager::class => ['rates' => ['code' => 'Code', 'item' => 'Item', 'description' => 'Description', 'category' => 'Category', 'unit' => 'Unit', 'rate' => 'Rate', 'currency' => 'Currency', 'supplier.name' => 'Supplier', 'verification_status' => 'Verification']],
    App\Livewire\Admin\SuppliersManager::class => ['suppliers' => ['name' => 'Name', 'type' => 'Type', 'contact_name' => 'Contact', 'phone' => 'Phone', 'email' => 'Email', 'location' => 'Location', 'website_url' => 'Website', 'is_active' => 'Active']],
    App\Livewire\Admin\QuotationsManager::class => ['quotations' => ['quote_number' => 'Quotation', 'supplier.name' => 'Supplier', 'status' => 'Status', 'currency' => 'Currency', 'total_amount' => 'Total', 'created_at' => 'Created']],
    App\Livewire\Admin\AiProviders::class => ['providers' => ['name' => 'Provider', 'provider_type' => 'Type', 'default_model' => 'Model', 'organisation_id' => 'Organisation', 'is_enabled' => 'Enabled', 'is_default' => 'Default', 'last_test_status' => 'Last test', 'last_test_message' => 'Test result', 'last_tested_at' => 'Tested at']],
    App\Livewire\Admin\PaymentGateways::class => ['gateways' => ['name' => 'Gateway', 'driver' => 'Provider', 'is_active' => 'Active', 'is_default' => 'Default']],
    App\Livewire\Admin\FaqsManager::class => ['faqs' => $faqs],
    App\Livewire\Admin\CategoriesManager::class => ['categories' => ['name' => 'Name', 'type' => 'Type', 'description' => 'Description', 'is_active' => 'Active']],
    App\Livewire\Admin\CurrenciesManager::class => ['currencies' => ['code' => 'Code', 'name' => 'Name', 'symbol' => 'Symbol', 'is_active' => 'Active', 'is_default' => 'Default']],
    App\Livewire\Admin\LanguagesManager::class => ['languages' => ['code' => 'Code', 'name' => 'Name', 'native_name' => 'Native name', 'is_active' => 'Active', 'is_default' => 'Default']],
    App\Livewire\Admin\RolesManager::class => ['roles' => ['name' => 'Role', 'slug' => 'Slug', 'users_count' => 'Users']],
    App\Livewire\Admin\HardwareScanner::class => ['categories' => ['name' => 'Category', 'items_count' => 'Items', 'is_active' => 'Active', 'last_scanned_at' => 'Last scanned']],
];
