<?php

namespace Tests\Feature;

use App\Models\ProductGroup;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\YieldStandard;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoDaysLeftTest extends TestCase
{
    use RefreshDatabase;

    public function test_days_remaining_use_signed_calendar_days_without_changing_deadline(): void
    {
        foreach (['00:00:00', '12:30:00', '23:59:59'] as $time) {
            $this->travelTo(now()->setDate(2026, 10, 26)->setTimeFromTimeString($time));

            foreach ([-7, -1, 0, 1, 7] as $days) {
                $deadline = today()->addDays($days)->toDateString();
                $po = new PurchaseOrder(['deadline' => $deadline]);

                $this->assertSame($days, $po->days_remaining_raw);
                $this->assertSame(max(0, $days), $po->days_remaining);
                $this->assertSame($deadline, $po->deadline->toDateString());
                $this->assertSame($days, $po->days_remaining_raw);
            }
        }
    }

    public function test_days_remaining_cross_midnight_and_calendar_boundaries(): void
    {
        foreach (['2026-10-31', '2026-12-31', '2028-02-28', '2028-02-29'] as $date) {
            $this->travelTo(\Illuminate\Support\Carbon::parse($date)->endOfDay());
            $po = new PurchaseOrder(['deadline' => today()->addDay()]);
            $deadline = $po->deadline->toDateString();

            $this->assertSame(1, $po->days_remaining_raw);
            $this->assertSame(1, $po->days_remaining);

            $this->travelTo(today()->addDay());
            $this->assertSame(0, $po->days_remaining_raw);
            $this->assertSame(0, $po->days_remaining);

            $this->travelTo(today()->addDay());
            $this->assertSame(-1, $po->days_remaining_raw);
            $this->assertSame(0, $po->days_remaining);
            $this->assertSame($deadline, $po->deadline->toDateString());
        }
    }

    public function test_dashboard_and_po_pages_agree_on_calendar_days(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(23, 59, 59));
        $user = User::create([
            'name' => 'Operator', 'email' => 'days@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($user);
        $group = ProductGroup::create(['name' => 'Ikan', 'color' => '#2563eb']);
        $type = ProductType::create([
            'product_group_id' => $group->id, 'name' => 'STEAK', 'code' => 'STK',
        ]);

        foreach ([-1, 0, 1, 7] as $days) {
            $po = PurchaseOrder::create([
                'po_number' => 'PO-DAYS-'.$days, 'buyer_name' => 'Buyer',
                'order_date' => today()->subDays(7), 'deadline' => today()->addDays($days),
                'status' => 'active', 'created_by' => $user->id,
            ]);
            $po->items()->create([
                'product_type_id' => $type->id, 'target_weight_kg' => 100,
                'target_box' => 20, 'weight_per_box_kg' => 5,
            ]);

            $service = app(DashboardService::class);
            $row = collect($service->getPOTable())->firstWhere('id', $po->id);
            $this->assertSame($days, $row['days_left']);
            $this->assertSame($days < 0, $row['is_overdue']);
            $alerts = collect($service->getSummary()['alerts'])->where('po_id', $po->id);
            $this->assertCount($days <= 0 ? 3 : 2, $alerts);
            $this->assertCount($days <= 0 ? 1 : 0, $alerts->where('category', 'deadline'));
            foreach ($alerts as $alert) {
                $this->assertSame($days, $alert['days_left']);
            }

            $label = $days < 0 ? 'Terlambat 1h' : ($days === 0 ? 'Hari ini!' : "$days hari lagi");
            $this->get(route('po.show', $po))->assertOk()->assertSee($label)
                ->assertSee('Deadline: <strong class="'.($days < 0 ? 'text-danger' : '').'">', false);
        }

        $this->get(route('po.index'))->assertOk()->assertSee('Terlambat 1h')
            ->assertSee('Hari ini')->assertSee('1h lagi')->assertSee('7h lagi');

        $this->travelTo(today()->addDay());
        $todayPo = PurchaseOrder::where('po_number', 'PO-DAYS-1')->sole();
        $row = collect($service->getPOTable())->firstWhere('id', $todayPo->id);
        $this->assertSame(0, $row['days_left']);
        $this->assertFalse($row['is_overdue']);
        $this->get(route('po.show', $todayPo))->assertOk()->assertSee('Hari ini!')
            ->assertDontSee('Terlambat')->assertSee('Deadline: <strong class="">', false);

        $yesterdayPo = PurchaseOrder::where('po_number', 'PO-DAYS-0')->sole();
        $row = collect($service->getPOTable())->firstWhere('id', $yesterdayPo->id);
        $this->assertSame(-1, $row['days_left']);
        $this->assertTrue($row['is_overdue']);

        $po->update(['status' => 'completed', 'deadline' => today()->subDay()]);
        $this->get(route('po.show', $po))->assertOk()->assertDontSee('Terlambat')
            ->assertSee('Deadline: <strong class="">', false);
    }

    public function test_yield_version_dates_use_whole_calendar_days(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(23, 59, 59));
        $user = User::create([
            'name' => 'Operator', 'email' => 'yield-days@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $previous = YieldStandard::create([
            'scope' => 'stage', 'min_yield_pct' => 70,
            'effective_from' => '2026-10-01', 'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('master.yield-standards.store'), [
            'min_yield_pct' => 75, 'effective_from' => '2026-10-27 18:30:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $current = YieldStandard::whereKeyNot($previous->id)->sole();
        $this->assertSame('2026-10-26', $previous->fresh()->effective_to->toDateString());
        $this->assertSame('2026-10-26', $previous->fresh()->getRawOriginal('effective_to'));
        $this->assertSame('2026-10-27', $current->effective_from->toDateString());
        $this->assertSame([$previous->id], YieldStandard::effectiveOn('2026-10-26')->pluck('id')->all());
        $this->assertSame([$current->id], YieldStandard::effectiveOn('2026-10-27')->pluck('id')->all());
        $this->assertSame([$previous->id], YieldStandard::effectiveOn('2026-10-26 23:59:59')->pluck('id')->all());
        $this->assertSame([$current->id], YieldStandard::effectiveOn('2026-10-27 00:00:00')->pluck('id')->all());
        $this->assertSame([], YieldStandard::effectiveOn('2026-09-30')->pluck('id')->all());
    }

    public function test_closing_yield_standard_uses_today_and_retains_history(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(23, 59, 59));
        $user = User::create([
            'name' => 'Operator', 'email' => 'yield-close@fishpro.test', 'password' => bcrypt('secret'),
        ]);
        $standard = YieldStandard::create([
            'scope' => 'stage', 'min_yield_pct' => 70,
            'effective_from' => '2026-10-01', 'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('master.yield-standards.close', $standard))
            ->assertRedirect()->assertSessionHasNoErrors();

        $standard->refresh();
        $this->assertSame('2026-10-26', $standard->effective_to->toDateString());
        $this->assertSame('00:00:00', $standard->effective_to->format('H:i:s'));
        $this->assertFalse($standard->is_active);
        $this->assertDatabaseHas('yield_standards', ['id' => $standard->id]);
        $this->assertSame([], YieldStandard::effectiveOn('2026-10-27')->pluck('id')->all());
        $this->get(route('master.yield-standards'))->assertOk()->assertSee('01/10/26 - 26/10/26');
    }
}
