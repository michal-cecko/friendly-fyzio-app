<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\ReservationStatus;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\CreateClient;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\EditClient;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ListClients;
use App\Filament\Clusters\Provoz\Resources\Clients\Pages\ViewClient;
use App\Filament\Clusters\Provoz\Resources\Clients\Widgets\ClientStatsOverview;
use App\Models\ClientProfile;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (['ViewAny:User', 'View:User', 'Create:User', 'Update:User', 'Delete:User'] as $name) {
            Permission::findOrCreate($name);
        }
    }

    public function test_admin_can_view_clients_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/provoz/clients')->assertSuccessful();
    }

    public function test_clients_list_shows_only_customers(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $customers = User::factory()->customer()->count(2)->create();
        $therapist = User::factory()->therapist()->create();

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords($customers)
            ->assertCanNotSeeTableRecords([$therapist]);
    }

    public function test_clients_list_can_be_searched_by_profile_city(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $match = User::factory()->customer()->create();
        ClientProfile::factory()->create(['user_id' => $match->getKey(), 'address_city' => 'Olomouc']);

        $other = User::factory()->customer()->create();
        ClientProfile::factory()->create(['user_id' => $other->getKey(), 'address_city' => 'Plzeň']);

        Livewire::test(ListClients::class)
            ->searchTable('Olomouc')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_clients_list_exposes_extra_toggleable_columns(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListClients::class)
            ->assertTableColumnExists('clientProfile.date_of_birth')
            ->assertTableColumnExists('clientProfile.company_ico');
    }

    public function test_client_is_created_with_customer_role(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Nový Klient',
                'email' => 'klient@example.test',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'klient@example.test')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->isCustomer());
        $this->assertFalse($created->hasAnyRole(['super_admin', 'admin', 'therapist']));
    }

    public function test_client_is_created_with_profile_and_billing_in_single_row(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Klient s profilem',
                'email' => 'profil@example.test',
                'clientProfile' => [
                    'address_city' => 'Brno',
                    'occupation' => 'Programátor',
                    'gender' => Gender::Female->value,
                    'birth_number' => '905728/5963',
                    'billing_name' => 'Firma s.r.o.',
                    'company_ico' => '12345678',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'profil@example.test')->first();

        $this->assertNotNull($created);
        $this->assertSame(1, ClientProfile::where('user_id', $created->getKey())->count());
        $this->assertSame('Brno', $created->clientProfile->address_city);
        $this->assertSame(Gender::Female, $created->clientProfile->gender);
        $this->assertSame('905728/5963', $created->clientProfile->birth_number);
        $this->assertSame('Firma s.r.o.', $created->clientProfile->billing_name);
        $this->assertSame('12345678', $created->clientProfile->company_ico);
    }

    public function test_client_profile_can_be_edited(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();
        ClientProfile::factory()->create([
            'user_id' => $client->getKey(),
            'address_city' => 'Praha',
        ]);

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->fillForm([
                'clientProfile' => [
                    'address_city' => 'Ostrava',
                    'billing_name' => 'Nový plátce',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, ClientProfile::where('user_id', $client->getKey())->count());
        $this->assertSame('Ostrava', $client->clientProfile->fresh()->address_city);
        $this->assertSame('Nový plátce', $client->clientProfile->fresh()->billing_name);
    }

    public function test_client_can_be_edited(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->fillForm(['name' => 'Upravený Klient'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Upravený Klient', $client->fresh()->name);
    }

    public function test_relation_managers_render_as_sections_not_tabs(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();

        $content = Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->instance()
            ->getRelationManagersContentComponent();

        $this->assertInstanceOf(Group::class, $content);
        $this->assertNotInstanceOf(Tabs::class, $content);
    }

    public function test_admin_can_view_client_detail(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();

        $this->get("/admin/provoz/clients/{$client->getKey()}")->assertSuccessful();
    }

    public function test_client_detail_shows_anamnesis(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();
        ClientProfile::factory()->create([
            'user_id' => $client->getKey(),
            'anamnesis' => 'Chronické bolesti bederní páteře po úrazu.',
        ]);

        $this->get("/admin/provoz/clients/{$client->getKey()}")
            ->assertSuccessful()
            ->assertSee('Chronické bolesti bederní páteře po úrazu.');
    }

    public function test_client_detail_has_create_reservation_cta_opening_the_booking_modal(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();

        Livewire::test(ViewClient::class, ['record' => $client->getKey()])
            ->assertActionExists('createReservation')
            ->mountAction('createReservation')
            ->assertActionMounted('createReservation')
            ->assertActionDataSet(['client_id' => $client->getKey()]);
    }

    public function test_client_detail_keeps_only_edit_outside_the_actions_dropdown(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();

        $page = Livewire::test(ViewClient::class, ['record' => $client->getKey()])->instance();
        $headerActions = (fn (): array => $this->getHeaderActions())->call($page);

        $this->assertCount(2, $headerActions);
        $this->assertInstanceOf(EditAction::class, $headerActions[0]);

        $group = $headerActions[1];
        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertSame('Další akce', $group->getLabel());
        $this->assertSame(
            ['createReservation', 'adjustCredit', 'impersonate', 'resetPassword', 'delete', 'activityLog'],
            array_map(fn ($action): string => $action->getName(), $group->getActions()),
        );
    }

    public function test_client_edit_page_offers_the_same_actions_as_the_detail_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();

        $page = Livewire::test(EditClient::class, ['record' => $client->getKey()])->instance();
        $headerActions = (fn (): array => $this->getHeaderActions())->call($page);

        $this->assertCount(2, $headerActions);
        $this->assertInstanceOf(ViewAction::class, $headerActions[0]);

        $group = $headerActions[1];
        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertSame('Další akce', $group->getLabel());
        $this->assertSame(
            ['createReservation', 'adjustCredit', 'impersonate', 'resetPassword', 'delete', 'forceDelete', 'restore', 'activityLog'],
            array_map(fn ($action): string => $action->getName(), $group->getActions()),
        );

        Livewire::test(EditClient::class, ['record' => $client->getKey()])
            ->mountAction('createReservation')
            ->assertActionMounted('createReservation')
            ->assertActionDataSet(['client_id' => $client->getKey()]);
    }

    public function test_client_stats_overview_renders_metrics(): void
    {
        $this->actingAs(User::factory()->admin()->revenue()->create());

        $client = User::factory()->customer()->create();
        Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'reservation_date' => now()->subDay()->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);

        Livewire::test(ClientStatsOverview::class, ['record' => $client])
            ->assertSee('Rezervace')
            ->assertSee('Poslední rezervace')
            ->assertSee('Kredit')
            ->assertSee('Utraceno')
            ->assertSee('Kurzy')
            ->assertSee(now()->subDay()->format('d.m.Y'));
    }

    public function test_client_spend_total_is_hidden_without_the_revenue_capability(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();

        Livewire::test(ClientStatsOverview::class, ['record' => $client])
            ->assertDontSee('Utraceno')
            // The non-money metrics stay visible.
            ->assertSee('Rezervace')
            ->assertSee('Kurzy');
    }

    public function test_clients_list_shows_date_of_last_reservation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $client = User::factory()->customer()->create();
        Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'reservation_date' => '2026-05-20',
        ]);
        Reservation::factory()->create([
            'client_id' => $client->getKey(),
            'reservation_date' => '2026-04-01',
        ]);

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$client])
            ->assertSee('20.05.2026')
            ->sortTable('last_reservation_at')
            ->assertCanSeeTableRecords([$client]);
    }

    public function test_non_customer_cannot_be_opened_via_clients_resource(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $therapist = User::factory()->therapist()->create();

        $this->get("/admin/provoz/clients/{$therapist->getKey()}/edit")->assertNotFound();
    }
}
