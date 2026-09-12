<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ $project->name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $project->status === 'active' ? 'bg-green-100 text-green-800' : ($project->status === 'completed' ? 'bg-blue-100 text-blue-800' : ($project->status === 'archived' ? 'bg-gray-100 text-gray-800' : 'bg-amber-100 text-amber-800')) }}">
                {{ $project->status }}
            </span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ url('/projects') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">
                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back
            </a>
            <a href="{{ url('/projects/' . $project->id . '/edit') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg transition">Edit</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Project Details</h2>
        </div>
        <div class="p-6">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Project Code</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->code ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Client</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->client ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Contractor</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->contractor ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Consultant</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->consultant ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Quantity Surveyor</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->quantity_surveyor ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Project Manager</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->project_manager ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Site Engineer</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->site_engineer ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Funding Organisation</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->funding_organisation ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Country</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->country ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">District</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->district ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Location</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->location ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Project Type</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->project_type ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Start Date</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->start_date?->format('M d, Y') ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Expected Completion</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->expected_completion_date?->format('M d, Y') ?? '?' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Contract Value</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ number_format((float) $project->contract_value, 2) }} {{ $project->currency }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Currency</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->currency }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $project->description ?? '?' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
