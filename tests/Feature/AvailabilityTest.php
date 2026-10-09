<?php

namespace Tests\Feature;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Actions\AvailabilityManager;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Support\Availability;
use App\Modules\Orders\Queries\ResponseStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\Support\AdminFixtures;
use Tests\Support\OrderFixtures;
use Tests\TestCase;

/** F-10 — disponibilité du freelance et temps de réponse calculé. */
class AvailabilityTest extends TestCase
{
    use AdminFixtures, OrderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->setUpParties();
    }

    private function setAvailability(bool $off, ?string $back = null, bool $auto = false)
    {
        return $this->actingAs($this->freelancer)->post('/freelance/disponibilite', array_filter(['available' => $off ? 'no' : 'yes', 'back_on' => $back, 'auto_reopen' => $auto ? '1' : null], fn ($v) => $v !== null));
    }

    public function test_when_unavailable_no_new_request_can_be_made_but_services_stay_visible_and_existing_orders_continue(): void
    {
        $pending = $this->placeOrder();                               // demande reçue AVANT l'indisponibilité
        $this->setAvailability(true, now()->addDays(10)->format('Y-m-d'))->assertSessionHasNoErrors();

        $this->actingAs($this->client)->get('/services/'.$this->service->slug)->assertOk()->assertSee('Demande impossible pour le moment')->assertSee('Indisponible jusqu’au');
        $this->actingAs($this->client)->get('/services/'.$this->service->slug.'/demande')->assertStatus(409)->assertSee('Freelance indisponible');
        $other = User::factory()->create();
        $before = DB::table('orders')->count();
        $this->actingAs($other)->post('/services/'.$this->service->slug.'/demande', $this->requestPayload())->assertStatus(409);
        $this->assertSame($before, DB::table('orders')->count());

        $this->accept($pending)->assertRedirect();                    // la demande déjà reçue reste traitable
        $this->assertSame('awaiting_payment', $pending->fresh()->state->value);

        $this->setAvailability(false)->assertSessionHasNoErrors();
        $this->actingAs($this->client)->get('/services/'.$this->service->slug.'/demande')->assertOk();
        $this->actingAs($this->client)->get('/services/'.$this->service->slug)->assertSee('Demander cette prestation')->assertDontSee('Demande impossible');
    }

    public function test_the_return_date_is_validated_and_the_automatic_reopening_needs_a_date(): void
    {
        $this->setAvailability(true, now()->subDay()->format('Y-m-d'))->assertSessionHasErrors('back_on');
        $this->setAvailability(true, now()->format('Y-m-d'))->assertSessionHasErrors('back_on');
        $this->setAvailability(true, now()->addYears(2)->format('Y-m-d'))->assertSessionHasErrors('back_on');
        $this->setAvailability(true, 'pas-une-date')->assertSessionHasErrors('back_on');
        $this->setAvailability(true, null, true)->assertSessionHasNoErrors();
        $p = FreelanceProfile::where('user_id', $this->freelancer->id)->first();
        $this->assertNotNull($p->unavailable_at);
        $this->assertFalse((bool) $p->auto_reopen, 'sans date, pas de retour automatique');
        $this->setAvailability(false);
        $this->assertNull($p->fresh()->unavailable_at);
    }

    public function test_the_return_date_reopens_automatically_when_chosen_and_the_task_notifies_once(): void
    {
        $this->setAvailability(true, now()->addDays(3)->format('Y-m-d'), true);
        $p = FreelanceProfile::where('user_id', $this->freelancer->id)->first();
        $this->assertTrue(Availability::unavailable($p));

        DB::table('freelance_profiles')->where('id', $p->id)->update(['back_on' => now()->format('Y-m-d')]);      // le jour du retour
        $p = $p->fresh();
        $this->assertFalse(Availability::unavailable($p), 'évalué à la lecture, sans attendre la tâche');
        $this->actingAs($this->client)->get('/services/'.$this->service->slug)->assertSee('Demander cette prestation');
        $this->actingAs($this->client)->get('/services/'.$this->service->slug.'/demande')->assertOk();

        $this->assertSame(1, app(AvailabilityManager::class)->reopenDue());
        $this->assertNull($p->fresh()->unavailable_at);
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->freelancer->id)->where('type', 'availability_reopened')->count());
        $this->assertSame(0, app(AvailabilityManager::class)->reopenDue(), 'idempotent');

        // sans retour automatique, une date échue n'ouvre rien : le freelance reste maître de sa disponibilité
        $this->setAvailability(true, now()->addDays(3)->format('Y-m-d'), false);
        DB::table('freelance_profiles')->where('id', $p->id)->update(['back_on' => now()->subDay()->format('Y-m-d')]);
        $this->assertTrue(Availability::unavailable($p->fresh()));
        $this->assertSame(0, app(AvailabilityManager::class)->reopenDue());
    }

    public function test_unavailable_services_stay_visible_but_come_after_the_available_ones(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->roles()->firstOrCreate(['role' => 'freelance']);
        $profile = FreelanceProfile::factory()->create(['user_id' => $otherUser->id, 'display_name' => 'Awa Disponible', 'headline' => 'Graphiste']);
        $available = Service::factory()->create(['freelance_profile_id' => $profile->id, 'title' => 'Service du disponible', 'published_at' => now()->subDays(5)]);
        $this->service->update(['title' => 'Service de l’indisponible', 'published_at' => now()->subDay()]);       // plus RÉCENT : passerait devant
        $this->setAvailability(true);

        $this->get('/services')->assertOk()->assertSeeInOrder(['Service du disponible', 'Service de l’indisponible'])->assertSee('Indisponible');
        $this->setAvailability(false);
        $this->get('/services')->assertSeeInOrder(['Service de l’indisponible', 'Service du disponible']);
        $this->assertNotNull($available);
    }

    public function test_the_response_time_is_computed_from_real_requests_and_hidden_below_five(): void
    {
        $this->assertNull(ResponseStats::summarize([])['label']);
        $row = fn (int $minutes, bool $answered = true) => ['requested_at' => '2026-10-01 10:00:00', 'answered_at' => $answered ? date('Y-m-d H:i:s', strtotime('2026-10-01 10:00:00') + $minutes * 60) : null, 'deadline_at' => '2026-10-03 10:00:00'];
        $this->assertNull(ResponseStats::summarize([$row(10), $row(10), $row(10), $row(10)])['label'], 'moins de 5 demandes : rien');
        $this->assertSame(['moins d’1 h', 100], [ResponseStats::summarize(array_fill(0, 5, $row(30)))['label'], ResponseStats::summarize(array_fill(0, 5, $row(30)))['rate']]);
        $this->assertSame('moins de 6 h', ResponseStats::summarize(array_fill(0, 6, $row(200)))['label']);
        $this->assertSame('moins de 24 h', ResponseStats::summarize(array_fill(0, 5, $row(600)))['label']);
        $this->assertSame('moins de 48 h', ResponseStats::summarize(array_fill(0, 5, $row(2000)))['label']);
        // sans réponse : compte comme la plus longue, et baisse le taux
        $mixed = ResponseStats::summarize([$row(10), $row(10), $row(10), $row(20, false), $row(20, false)]);
        $this->assertSame(['moins d’1 h', 60], [$mixed['label'], $mixed['rate']]);
        $mostlyMissed = ResponseStats::summarize([$row(10), $row(10), $row(20, false), $row(20, false), $row(20, false)]);
        $this->assertNull($mostlyMissed['label'], 'la médiane tombe sur une demande sans réponse : aucune tranche affichée');
        $this->assertSame(40, $mostlyMissed['rate']);
    }

    public function test_real_requests_feed_the_stats_and_withdrawn_or_mission_orders_are_ignored(): void
    {
        $orders = [];
        for ($i = 0; $i < 6; $i++) {
            $client = User::factory()->create();
            $orders[] = $this->placeOrder($client);
        }
        $rt = now()->subDays(3);
        foreach ($orders as $i => $o) {
            DB::table('orders')->where('id', $o->id)->update(['requested_at' => $rt, 'response_deadline_at' => $rt->copy()->addHours(48)]);
        }
        foreach (array_slice($orders, 0, 4) as $o) {                                    // 4 acceptées en 2 h
            DB::table('orders')->where('id', $o->id)->update(['state' => 'awaiting_payment', 'accepted_at' => $rt->copy()->addHours(2)]);
        }
        DB::table('orders')->where('id', $orders[4]->id)->update(['state' => 'expired', 'closure_reason' => 'expired_acceptance', 'closed_at' => $rt->copy()->addHours(48)]);
        DB::table('orders')->where('id', $orders[5]->id)->update(['state' => 'cancelled', 'closure_reason' => 'withdrawn', 'closed_at' => $rt->copy()->addHours(1)]);   // retirée : non comptée

        $s = app(ResponseStats::class)->forFreelancers([$this->freelancer->id])[$this->freelancer->id];
        $this->assertSame(5, $s['count']);
        $this->assertSame(['moins de 6 h', 80], [$s['label'], $s['rate']]);
        $this->get('/services/'.$this->service->slug)->assertSee('Répond en moins de 6 h')->assertSee('80 % traitées dans le délai');
        $this->get('/freelances')->assertOk();
        $this->actingAs($this->freelancer)->get('/freelance/disponibilite')->assertOk()->assertSee('moins de 6 h')->assertSee('80 %');
        $this->actingAs($this->freelancer)->get('/freelance')->assertOk()->assertSee('Disponibilité');
    }

    public function test_a_new_freelancer_is_shown_as_new_and_the_page_is_reserved_to_freelancers(): void
    {
        $this->get('/services/'.$this->service->slug)->assertSee('Nouveau sur FreeCI');
        $this->actingAs($this->client)->get('/freelance/disponibilite')->assertRedirect();
        $this->get('/freelance/disponibilite')->assertRedirect();
    }

    public function test_the_administration_sees_the_availability_but_has_no_way_to_change_it(): void
    {
        $this->setAvailability(true, now()->addDays(5)->format('Y-m-d'));
        $admin = $this->readyAdmin();
        $this->asAdmin($admin)->get("/admin/utilisateurs/{$this->freelancer->id}")->assertOk()->assertSee('Disponibilité')->assertSee('Indisponible jusqu’au');
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($r) => str_starts_with($r->uri(), 'admin') && str_contains($r->uri(), 'disponibilite')));
    }
}
