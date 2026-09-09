<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'module' => 'dashboard'],

            // Projects
            ['name' => 'View Projects', 'slug' => 'projects.view', 'module' => 'projects'],
            ['name' => 'Create Projects', 'slug' => 'projects.create', 'module' => 'projects'],
            ['name' => 'Edit Projects', 'slug' => 'projects.edit', 'module' => 'projects'],
            ['name' => 'Delete Projects', 'slug' => 'projects.delete', 'module' => 'projects'],

            // BOQ
            ['name' => 'View BOQs', 'slug' => 'boq.view', 'module' => 'boq'],
            ['name' => 'Create BOQs', 'slug' => 'boq.create', 'module' => 'boq'],
            ['name' => 'Edit BOQs', 'slug' => 'boq.edit', 'module' => 'boq'],
            ['name' => 'Delete BOQs', 'slug' => 'boq.delete', 'module' => 'boq'],
            ['name' => 'Upload BOQs', 'slug' => 'boq.upload', 'module' => 'boq'],
            ['name' => 'Approve BOQs', 'slug' => 'boq.approve', 'module' => 'boq'],

            // AI
            ['name' => 'Use AI Extraction', 'slug' => 'ai.boq.extraction', 'module' => 'ai'],
            ['name' => 'Use AI Pricing', 'slug' => 'ai.pricing.analysis', 'module' => 'ai'],
            ['name' => 'Use AI Translation', 'slug' => 'ai.translation', 'module' => 'ai'],

            // Rate Library
            ['name' => 'View Rate Library', 'slug' => 'rates.view', 'module' => 'rates'],
            ['name' => 'Manage Rate Library', 'slug' => 'rates.manage', 'module' => 'rates'],

            // Suppliers
            ['name' => 'View Suppliers', 'slug' => 'suppliers.view', 'module' => 'suppliers'],
            ['name' => 'Manage Suppliers', 'slug' => 'suppliers.manage', 'module' => 'suppliers'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view', 'module' => 'reports'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'module' => 'reports'],

            // Approvals
            ['name' => 'View Approvals', 'slug' => 'approvals.view', 'module' => 'approvals'],
            ['name' => 'Approve Requests', 'slug' => 'approvals.approve', 'module' => 'approvals'],

            // Subscriptions
            ['name' => 'View Subscriptions', 'slug' => 'subscriptions.view', 'module' => 'subscriptions'],
            ['name' => 'Manage Subscriptions', 'slug' => 'subscriptions.manage', 'module' => 'subscriptions'],

            // Payments
            ['name' => 'View Payments', 'slug' => 'payments.view', 'module' => 'payments'],
            ['name' => 'Process Payments', 'slug' => 'payments.process', 'module' => 'payments'],

            // Invoices
            ['name' => 'View Invoices', 'slug' => 'invoices.view', 'module' => 'invoices'],
            ['name' => 'Manage Invoices', 'slug' => 'invoices.manage', 'module' => 'invoices'],

            // Top-ups
            ['name' => 'View Top-ups', 'slug' => 'topups.view', 'module' => 'topups'],
            ['name' => 'Purchase Top-ups', 'slug' => 'topups.purchase', 'module' => 'topups'],

            // Translation management
            ['name' => 'View Translations', 'slug' => 'translations.view', 'module' => 'translations'],
            ['name' => 'Edit Translations', 'slug' => 'translations.edit', 'module' => 'translations'],
            ['name' => 'Publish Translations', 'slug' => 'translations.publish', 'module' => 'translations'],

            // Admin settings
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'module' => 'settings'],
            ['name' => 'Manage Plans', 'slug' => 'plans.manage', 'module' => 'settings'],
            ['name' => 'Manage Features', 'slug' => 'features.manage', 'module' => 'settings'],
            ['name' => 'Manage Versions', 'slug' => 'versions.manage', 'module' => 'settings'],
            ['name' => 'Manage Gateways', 'slug' => 'gateways.manage', 'module' => 'settings'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'module' => 'settings'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'settings'],
            ['name' => 'Manage Organisations', 'slug' => 'organisations.manage', 'module' => 'settings'],
            ['name' => 'Manage Languages', 'slug' => 'languages.manage', 'module' => 'settings'],
            ['name' => 'View Audit Logs', 'slug' => 'audit.view', 'module' => 'settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full system access.',
                'is_system' => true,
                'permissions' => '*',
            ],
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Organisation administrator with broad management access.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view', 'projects.create', 'projects.edit', 'projects.delete',
                    'boq.view', 'boq.create', 'boq.edit', 'boq.delete', 'boq.upload', 'boq.approve',
                    'ai.boq.extraction', 'ai.pricing.analysis', 'ai.translation',
                    'rates.view', 'rates.manage',
                    'suppliers.view', 'suppliers.manage',
                    'reports.view', 'reports.export',
                    'approvals.view', 'approvals.approve',
                    'subscriptions.view', 'subscriptions.manage',
                    'payments.view', 'payments.process',
                    'invoices.view', 'invoices.manage',
                    'topups.view', 'topups.purchase',
                    'translations.view', 'translations.edit', 'translations.publish',
                    'settings.manage', 'plans.manage', 'features.manage', 'versions.manage',
                    'gateways.manage', 'roles.manage', 'users.manage', 'organisations.manage',
                    'languages.manage', 'audit.view',
                ],
            ],
            [
                'name' => 'Project Manager',
                'slug' => 'project-manager',
                'description' => 'Manages projects and BOQs.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view', 'projects.create', 'projects.edit',
                    'boq.view', 'boq.create', 'boq.edit', 'boq.upload', 'boq.approve',
                    'ai.boq.extraction', 'ai.pricing.analysis', 'ai.translation',
                    'rates.view',
                    'reports.view', 'reports.export',
                    'approvals.view', 'approvals.approve',
                ],
            ],
            [
                'name' => 'Quantity Surveyor',
                'slug' => 'quantity-surveyor',
                'description' => 'Reviews and prices BOQ items.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view', 'boq.edit', 'boq.approve',
                    'ai.boq.extraction', 'ai.pricing.analysis', 'ai.translation',
                    'rates.view', 'rates.manage',
                    'reports.view', 'reports.export',
                    'approvals.view', 'approvals.approve',
                ],
            ],
            [
                'name' => 'Site Engineer',
                'slug' => 'site-engineer',
                'description' => 'Views projects and BOQs on site.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view',
                    'rates.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Civil Engineer',
                'slug' => 'civil-engineer',
                'description' => 'Views and reviews civil works projects.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view',
                    'rates.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Procurement Officer',
                'slug' => 'procurement-officer',
                'description' => 'Manages suppliers and quotations.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view',
                    'suppliers.view', 'suppliers.manage',
                    'rates.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Finance',
                'slug' => 'finance',
                'description' => 'Manages payments and invoices.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'subscriptions.view',
                    'payments.view', 'payments.process',
                    'invoices.view', 'invoices.manage',
                    'topups.view', 'topups.purchase',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'Contractor',
                'slug' => 'contractor',
                'description' => 'Views assigned projects and BOQs.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Consultant',
                'slug' => 'consultant',
                'description' => 'Views projects and provides review.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Client',
                'slug' => 'client',
                'description' => 'Views their projects and reports.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access.',
                'is_system' => true,
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'boq.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Translator',
                'slug' => 'translator',
                'description' => 'Reviews and edits translations.',
                'is_system' => true,
                'permissions' => [
                    'translations.view', 'translations.edit',
                    'boq.view',
                ],
            ],
            [
                'name' => 'Language Administrator',
                'slug' => 'language-administrator',
                'description' => 'Manages languages and translations.',
                'is_system' => true,
                'permissions' => [
                    'languages.manage',
                    'translations.view', 'translations.edit', 'translations.publish',
                ],
            ],
            [
                'name' => 'Billing Administrator',
                'slug' => 'billing-administrator',
                'description' => 'Manages billing, invoices and payments.',
                'is_system' => true,
                'permissions' => [
                    'subscriptions.view', 'subscriptions.manage',
                    'payments.view', 'payments.process',
                    'invoices.view', 'invoices.manage',
                    'topups.view', 'topups.purchase',
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name' => 'Subscription Manager',
                'slug' => 'subscription-manager',
                'description' => 'Manages subscriptions and plans.',
                'is_system' => true,
                'permissions' => [
                    'subscriptions.view', 'subscriptions.manage',
                    'plans.manage',
                    'topups.view', 'topups.purchase',
                    'reports.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissionSlugs = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::updateOrCreate(['slug' => $roleData['slug']], $roleData);

            if ($permissionSlugs === '*') {
                $role->permissions()->sync(Permission::pluck('id'));
            } else {
                $role->permissions()->sync(Permission::whereIn('slug', $permissionSlugs)->pluck('id'));
            }
        }
    }
}
