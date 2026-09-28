<?php

namespace Tests\Feature;

use App\Livewire\Admin\SuppliersManager;
use App\Livewire\Profile\Index as ProfileIndex;
use App\Mail\BoqShared;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Entitlement;
use App\Models\Feature;
use App\Models\HardwarePrice;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BoqPdfService;
use App\Services\SupplierCsvImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin'])->id);

        return $user;
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::CUSTOMER)->value('id'));

        foreach (['boq.management'] as $code) {
            Entitlement::factory()->create([
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'feature_id' => Feature::firstOrCreate(['code' => $code], ['name' => $code])->id,
                'status' => 'active',
                'expires_at' => now()->addMonth(),
            ]);
        }

        return $user;
    }

    private function boqFor(User $user): Boq
    {
        $project = Project::factory()->create(['user_id' => $user->id, 'organisation_id' => $user->organisation_id, 'client' => 'City Council']);
        $this->actingAs($user);
        $boq = Boq::factory()->create(['project_id' => $project->id, 'organisation_id' => $user->organisation_id, 'name' => 'Main Block', 'currency' => 'USD']);
        BoqItem::factory()->create(['boq_id' => $boq->id, 'description' => 'Excavation', 'unit' => 'm3', 'quantity' => 10, 'approved_rate' => 12.5, 'amount' => 125]);
        auth()->guard('web')->logout();

        return $boq;
    }

    // ---------------------------------------------------------------- roles & errors

    public function test_self_registered_users_get_the_user_role_with_price_access(): void
    {
        $this->assertFalse(Role::where('slug', 'viewer')->exists());

        $user = $this->customer();

        $this->assertTrue($user->hasPermission('hardware-prices.view'));
        $this->assertFalse($user->hasPermission('hardware-prices.manage'));
        $this->actingAs($user)->get(route('hardware-prices.index'))->assertOk();
    }

    public function test_viewer_role_is_migrated_to_user_without_losing_members(): void
    {
        $role = Role::where('slug', Role::CUSTOMER)->firstOrFail();
        $role->update(['slug' => 'viewer', 'name' => 'Viewer']);
        $member = User::factory()->create();
        $member->roles()->attach($role->id);

        (require database_path('migrations/2026_09_28_000001_rename_viewer_role_to_user.php'))->up();

        $this->assertTrue($member->fresh()->hasRole('user'));
        $this->assertSame('User', $role->fresh()->name);
    }

    public function test_forbidden_pages_show_the_branded_403_without_internals(): void
    {
        $this->actingAs($this->customer())
            ->get(route('admin.settings'))
            ->assertForbidden()
            ->assertSee('403 – Access Denied')
            ->assertSee('You do not have permission to perform this action.')
            ->assertSee('Return to Dashboard')
            ->assertDontSee('role:super-admin')
            ->assertDontSee('Symfony');

        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/hardware-prices/fetch')
            ->assertForbidden()
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace');
    }

    // ---------------------------------------------------------------- suppliers

    public function test_supplier_statistics_and_type_filter(): void
    {
        Supplier::factory()->create(['type' => 'supplier', 'is_active' => true]);
        Supplier::factory()->create(['type' => 'supplier', 'is_active' => false]);
        Supplier::factory()->create(['type' => 'factory', 'is_active' => true, 'name' => 'Big Cement Factory']);

        Livewire::actingAs($this->superAdmin())
            ->test(SuppliersManager::class)
            ->assertViewHas('stats', fn ($stats) => $stats['suppliers'] === 2 && $stats['active_suppliers'] === 1 && $stats['factories'] === 1)
            ->call('filterBy', 'factory')
            ->assertSee('Big Cement Factory')
            ->assertViewHas('suppliers', fn ($page) => $page->total() === 1);
    }

    public function test_csv_import_validates_detects_duplicates_and_imports(): void
    {
        Supplier::factory()->create(['name' => 'Existing Hardware', 'email' => 'old@example.com']);

        $csv = implode("\n", [
            implode(',', SupplierCsvImporter::HEADERS),
            'New Supplier,Ann,+256700000001,new@example.com,newsupplier.com,Uganda,Kampala,Plot 1,supplier,active,',
            'Duplicate Email Co,Bob,,old@example.com,,,,,supplier,active,',
            ',NoName,,,,,,,supplier,active,',
            'Bad Type Ltd,Cy,,bad@example.com,,,,,shop,active,',
            'Cement Works,Dee,+254700000002,,https://cementworks.example,KE,Mombasa,,factory,inactive,',
            'new supplier,Eve,,,,,,,supplier,active,',
        ])."\n";

        $component = Livewire::actingAs($this->superAdmin())
            ->test(SuppliersManager::class)
            ->call('openImport')
            ->set('importFile', UploadedFile::fake()->createWithContent('suppliers.csv', $csv))
            ->call('previewImport')
            ->assertHasNoErrors();

        $preview = $component->get('importPreview');
        $this->assertCount(2, $preview['valid']);
        $this->assertCount(2, $preview['duplicates']);
        $this->assertCount(2, $preview['failed']);

        $component->call('confirmImport');

        $factory = Supplier::where('name', 'Cement Works')->firstOrFail();
        $this->assertSame('factory', $factory->type);
        $this->assertFalse($factory->is_active);
        $this->assertSame('KE', $factory->country);
        $this->assertSame('https://newsupplier.com', Supplier::where('name', 'New Supplier')->value('website_url'));
    }

    // ---------------------------------------------------------------- prices

    public function test_users_can_filter_and_see_lowest_and_highest_prices(): void
    {
        $user = $this->customer();
        foreach ([[100, 'Hima'], [140, 'Tororo'], [120, 'Hima']] as [$price, $brand]) {
            HardwarePrice::create([
                'organisation_id' => $user->organisation_id, 'item_name' => 'Cement 50kg', 'brand' => $brand,
                'category' => 'Cement', 'price_type' => 'hardware', 'specification' => '42.5N', 'unit' => 'bag',
                'price' => $price, 'currency' => 'USD', 'supplier' => 'Shop '.$price, 'location' => 'Kampala',
                'source_reference' => 'test', 'fetched_at' => now(), 'is_active' => true,
            ]);
        }

        $this->actingAs($user)->get(route('hardware-prices.index', ['brand' => 'Hima']))->assertOk();

        Livewire::actingAs($user)
            ->withQueryParams(['brand' => 'Hima', 'sort' => 'price_asc'])
            ->test(\App\Livewire\HardwarePrices\Index::class)
            ->assertViewHas('prices', fn ($page) => $page->total() === 2 && (float) $page->first()->price === 100.0)
            ->assertSee(__('Lowest available price for this item'));
    }

    // ---------------------------------------------------------------- company & PDF

    public function test_user_can_save_company_profile_with_logo(): void
    {
        Storage::fake('public');
        $user = $this->customer();

        Livewire::actingAs($user)
            ->test(ProfileIndex::class)
            ->call('setActiveTab', 'company')
            ->set('companyForm.company_name', 'Riverside Builders')
            ->set('companyForm.website', 'riverside.example')
            ->set('companyLogo', UploadedFile::fake()->image('logo.png', 200, 200))
            ->call('saveCompanyProfile')
            ->assertHasNoErrors();

        $profile = $user->fresh()->companyProfile;
        $this->assertSame('Riverside Builders', $profile->company_name);
        $this->assertSame('https://riverside.example', $profile->website);
        Storage::disk('public')->assertExists($profile->logo_path);
    }

    public function test_pdf_uses_the_owners_branding_and_freezes_it(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $owner = $this->customer();
        $owner->companyProfile()->create(['company_name' => 'Owner Construction Ltd']);
        $boq = $this->boqFor($owner);

        $this->assertSame($owner->id, $boq->owner_id);

        // Someone else in the same organisation downloads it: still the owner's brand.
        $colleague = $this->customer();
        $colleague->update(['organisation_id' => $owner->organisation_id]);
        $colleague->companyProfile()->create(['company_name' => 'Colleague Ltd']);

        $html = view('pdf.boq', $this->pdfData($boq))->render();
        $this->assertStringContainsString('Owner Construction Ltd', $html);
        $this->assertStringNotContainsString('Colleague Ltd', $html);

        $this->actingAs($colleague)->get(route('boqs.pdf', $boq))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame('Owner Construction Ltd', $boq->fresh()->company_snapshot['company_name']);

        // Later profile changes do not rebrand an already exported BOQ.
        $owner->companyProfile->update(['company_name' => 'Renamed Ltd']);
        $this->assertSame('Owner Construction Ltd', $boq->fresh()->brandingIdentity()['company_name']);
    }

    public function test_boq_owner_cannot_be_reassigned(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);
        $other = $this->customer();

        $boq->update(['owner_id' => $other->id]);

        $this->assertSame($owner->id, $boq->fresh()->owner_id);
    }

    public function test_pdf_preview_is_inline(): void
    {
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        $this->actingAs($owner)
            ->get(route('boqs.pdf', ['boq' => $boq, 'inline' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('inline', (string) $this->actingAs($owner)->get(route('boqs.pdf', ['boq' => $boq, 'inline' => 1]))->headers->get('content-disposition'));
    }

    public function test_boq_can_be_shared_by_email_and_signed_link(): void
    {
        Mail::fake();
        $owner = $this->customer();
        $boq = $this->boqFor($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/boqs/{$boq->id}/share/email", ['email' => 'client@example.com', 'message' => 'Please review'])
            ->assertOk();

        Mail::assertSent(BoqShared::class, fn (BoqShared $mail) => $mail->hasTo('client@example.com') && count($mail->attachments()) === 1);

        $link = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/boqs/{$boq->id}/share/link")
            ->assertOk()
            ->assertJsonPath('data.expires_in_days', 7)
            ->json('data.url');

        $this->assertStringContainsString('wa.me', $this->actingAs($owner, 'sanctum')->getJson("/api/v1/boqs/{$boq->id}/share/link")->json('data.whatsapp_url'));

        auth()->guard('web')->logout();
        $this->get($link)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('boqs.shared-pdf', $boq))->assertForbidden(); // unsigned
    }

    public function test_other_customers_cannot_share_or_download_a_boq(): void
    {
        $boq = $this->boqFor($this->customer());
        $intruder = $this->customer();

        $this->actingAs($intruder, 'sanctum')->getJson("/api/v1/boqs/{$boq->id}/share/link")->assertForbidden();
        $this->actingAs($intruder, 'sanctum')->postJson("/api/v1/boqs/{$boq->id}/share/email", ['email' => 'x@example.com'])->assertForbidden();
    }

    public function test_company_profile_api(): void
    {
        Storage::fake('public');
        $user = $this->customer();

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/company-profile', [
                'company_name' => 'Mobile Co',
                'telephone' => '+256 700 111 222',
                'logo' => UploadedFile::fake()->image('logo.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Mobile Co');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/company-profile')->assertJsonPath('data.telephone', '+256 700 111 222');
    }

    /** @return array<string, mixed> */
    private function pdfData(Boq $boq): array
    {
        $boq->loadMissing('project', 'owner');

        return [
            'boq' => $boq, 'project' => $boq->project, 'company' => $boq->brandingIdentity() ?? [], 'logo' => null,
            'lines' => collect(), 'subtotal' => 0, 'taxRate' => 0, 'taxLabel' => 'VAT', 'tax' => 0, 'total' => 0,
            'currency' => 'USD', 'preparedBy' => $boq->owner?->name, 'generatedAt' => now(),
        ];
    }
}
