<?php

namespace Tests\Feature;

use App\Models\PackingBox;
use App\Models\PoItem;
use App\Models\ProductGroup;
use App\Models\ProductType;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: mengedit PO pernah menghapus SELURUH riwayat packing PO tersebut.
 *
 * Penyebabnya `PurchaseOrderController@update` menjalankan `$po->items()->delete()`
 * sementara FK po_item_id memakai cascadeOnDelete.
 */
class PoUpdateDoesNotDeletePackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PurchaseOrder $po;
    private PoItem $item;
    private ProductType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator', 'email' => 'op@fishpro.test', 'password' => bcrypt('secret'),
        ]);

        $group = ProductGroup::create(['name' => 'Ikan', 'color' => '#2563eb', 'is_active' => true]);
        $this->type = ProductType::create([
            'product_group_id' => $group->id, 'name' => 'YF STEAK', 'code' => 'YFSTK', 'is_active' => true,
        ]);

        $this->po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001', 'buyer_name' => 'Buyer Lama',
            'order_date' => today()->subDays(3), 'deadline' => today()->addDays(7),
            'status' => 'active', 'created_by' => $this->user->id,
        ]);

        $this->item = $this->po->items()->create([
            'product_type_id' => $this->type->id, 'target_weight_kg' => 100,
            'target_box' => 20, 'weight_per_box_kg' => 5,
        ]);

        // Empat box fisik yang sudah terlanjur dipacking.
        foreach (range(1, 4) as $i) {
            PackingBox::create([
                'box_code'          => sprintf('BOX-POTEST001-YFSTK-%04d', $i),
                'qr_code'           => 'QRTEST' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'purchase_order_id' => $this->po->id,
                'po_item_id'        => $this->item->id,
                'product_type_id'   => $this->type->id,
                'sequence_no'       => $i,
                'net_weight_kg'     => 5,
                'status'            => 'packed',
                'packing_date'      => today(),
                'created_by'        => $this->user->id,
            ]);
        }
    }

    public function test_mengedit_po_tidak_menghapus_data_packing(): void
    {
        $response = $this->actingAs($this->user)->put(route('po.update', $this->po), [
            'buyer_name' => 'Buyer Baru',
            'order_date' => $this->po->order_date->format('Y-m-d'),
            'deadline'   => $this->po->deadline->format('Y-m-d'),
            'status'     => 'in_progress',
            'items'      => [[
                'id'                => $this->item->id,
                'product_type_id'   => $this->item->product_type_id,
                'target_weight_kg'  => 150,
                'target_box'        => 30,
                'weight_per_box_kg' => 5,
            ]],
        ]);

        $response->assertRedirect(route('po.show', $this->po));

        $this->assertDatabaseCount('packing_boxes', 4);
        $this->assertDatabaseHas('packing_boxes', [
            'po_item_id' => $this->item->id,
            'box_code'   => 'BOX-POTEST001-YFSTK-0001',
        ]);

        // Item di-update di tempat, bukan dihapus lalu dibuat ulang dengan id baru.
        $this->assertDatabaseHas('po_items', [
            'id'               => $this->item->id,
            'target_weight_kg' => 150,
            'target_box'       => 30,
            'deleted_at'       => null,
        ]);
        $this->assertSame('Buyer Baru', $this->po->fresh()->buyer_name);
    }

    public function test_item_yang_sudah_dipacking_tidak_boleh_dibuang_dari_po(): void
    {
        $response = $this->actingAs($this->user)
            ->from(route('po.edit', $this->po))
            ->put(route('po.update', $this->po), [
                'buyer_name' => 'Buyer Baru',
                'order_date' => $this->po->order_date->format('Y-m-d'),
                'deadline'   => $this->po->deadline->format('Y-m-d'),
                'status'     => 'active',
                // item lama sengaja dihilangkan, diganti item baru
                'items'      => [[
                    'product_type_id'  => $this->item->product_type_id,
                    'target_weight_kg' => 50,
                    'target_box'       => 10,
                ]],
            ]);

        $response->assertRedirect(route('po.edit', $this->po));
        $response->assertSessionHasErrors('items');

        $this->assertDatabaseCount('packing_boxes', 4);
        $this->assertDatabaseHas('po_items', ['id' => $this->item->id, 'deleted_at' => null]);
        $this->assertSame('Buyer Lama', $this->po->fresh()->buyer_name, 'Perubahan harus batal seluruhnya.');
    }
}
