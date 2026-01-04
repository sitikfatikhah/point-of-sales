<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Inventory Adjustment dengan Nomor Jurnal
 *
 * Test ini memastikan:
 * - Adjustment hanya bisa dibuat dengan nomor jurnal
 * - Nomor jurnal digenerate otomatis dengan format ADJ + YYYYMMDD + 4 digit sequence
 * - Adjustment tercatat di kedua tabel: inventory_adjustments dan stock_movements
 * - Tipe adjustment yang valid: adjustment_in, adjustment_out, return, damage, correction
 */
class InventoryAdjustmentJurnalTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected User $user;
    protected Category $kategori;
    protected Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();

        $this->user = User::factory()->create([
            'name' => 'Admin Gudang',
        ]);

        $this->kategori = Category::create([
            'name' => 'Minuman',
            'description' => 'Kategori minuman',
        ]);

        $this->produk = Product::create([
            'barcode' => 'MNM001',
            'title' => 'Air Mineral',
            'description' => 'Air mineral 600ml',
            'category_id' => $this->kategori->id,
            'sell_price' => 5000,
            'stock' => 100,
        ]);

        // Buat stock movement awal (simulasi dari purchase)
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 2000,
            'total_price' => 200000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);
    }

    // ==========================================
    // TEST GENERATE NOMOR JURNAL
    // ==========================================

    /** @test */
    public function dapat_generate_nomor_jurnal_dengan_format_benar(): void
    {
        $journalNumber = InventoryAdjustment::generateJournalNumber();

        $today = now()->format('Ymd');
        $expectedPattern = '/^ADJ' . $today . '\d{4}$/';

        $this->assertMatchesRegularExpression($expectedPattern, $journalNumber);
    }

    /** @test */
    public function nomor_jurnal_increment_setiap_adjustment_baru(): void
    {
        $this->actingAs($this->user);

        $result1 = $this->inventoryService->createAdjustment(
            $this->produk,
            10,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Adjustment 1'
        );

        $result2 = $this->inventoryService->createAdjustment(
            $this->produk,
            5,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Adjustment 2'
        );

        $result3 = $this->inventoryService->createAdjustment(
            $this->produk,
            3,
            InventoryAdjustment::TYPE_ADJUSTMENT_OUT,
            'Adjustment 3'
        );

        $journal1 = $result1['adjustment']->journal_number;
        $journal2 = $result2['adjustment']->journal_number;
        $journal3 = $result3['adjustment']->journal_number;

        // Extract sequence number (last 4 digits)
        $seq1 = substr($journal1, -4);
        $seq2 = substr($journal2, -4);
        $seq3 = substr($journal3, -4);

        $this->assertEquals('0001', $seq1);
        $this->assertEquals('0002', $seq2);
        $this->assertEquals('0003', $seq3);
    }

    // ==========================================
    // TEST TIPE ADJUSTMENT
    // ==========================================

    /** @test */
    public function tipe_adjustment_yang_valid(): void
    {
        $validTypes = InventoryAdjustment::getTypes();

        $this->assertContains('adjustment_in', $validTypes);
        $this->assertContains('adjustment_out', $validTypes);
        $this->assertContains('return', $validTypes);
        $this->assertContains('damage', $validTypes);
        $this->assertContains('correction', $validTypes);

        // Tidak boleh ada purchase dan sale (sudah dipindah ke StockMovement)
        $this->assertNotContains('purchase', $validTypes);
        $this->assertNotContains('sale', $validTypes);
        $this->assertNotContains('in', $validTypes);
        $this->assertNotContains('out', $validTypes);
    }

    /** @test */
    public function incoming_types_menambah_stok(): void
    {
        $incomingTypes = InventoryAdjustment::getIncomingTypes();

        $this->assertContains('adjustment_in', $incomingTypes);
        $this->assertContains('return', $incomingTypes);
    }

    /** @test */
    public function outgoing_types_mengurangi_stok(): void
    {
        $outgoingTypes = InventoryAdjustment::getOutgoingTypes();

        $this->assertContains('adjustment_out', $outgoingTypes);
        $this->assertContains('damage', $outgoingTypes);
    }

    // ==========================================
    // TEST ADJUSTMENT MASUK
    // ==========================================

    /** @test */
    public function adjustment_masuk_menambah_stok(): void
    {
        $this->actingAs($this->user);

        $currentStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(100, $currentStock);

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            50,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Barang ditemukan di gudang'
        );

        $newStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(150, $newStock);

        // Verifikasi record di inventory_adjustments
        $this->assertDatabaseHas('inventory_adjustments', [
            'id' => $result['adjustment']->id,
            'product_id' => $this->produk->id,
            'type' => 'adjustment_in',
            'quantity_change' => 50,
        ]);

        // Verifikasi record di stock_movements
        $this->assertDatabaseHas('stock_movements', [
            'id' => $result['movement']->id,
            'product_id' => $this->produk->id,
            'movement_type' => 'adjustment_in',
            'quantity' => 50,
            'quantity_after' => 150,
        ]);
    }

    // ==========================================
    // TEST ADJUSTMENT KELUAR
    // ==========================================

    /** @test */
    public function adjustment_keluar_mengurangi_stok(): void
    {
        $this->actingAs($this->user);

        $currentStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(100, $currentStock);

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            30,
            InventoryAdjustment::TYPE_ADJUSTMENT_OUT,
            'Barang hilang'
        );

        $newStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(70, $newStock);

        // Verifikasi quantity_change negatif di stock_movements
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'adjustment_out',
            'quantity' => -30, // Negatif
        ]);
    }

    /** @test */
    public function damage_adjustment_mengurangi_stok(): void
    {
        $this->actingAs($this->user);

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            5,
            InventoryAdjustment::TYPE_DAMAGE,
            'Botol pecah'
        );

        $newStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(95, $newStock);

        $this->assertDatabaseHas('inventory_adjustments', [
            'type' => 'damage',
            'reason' => 'Botol pecah',
        ]);
    }

    /** @test */
    public function return_adjustment_menambah_stok(): void
    {
        $this->actingAs($this->user);

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            10,
            InventoryAdjustment::TYPE_RETURN,
            'Barang dikembalikan customer'
        );

        $newStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(110, $newStock);

        $this->assertDatabaseHas('inventory_adjustments', [
            'type' => 'return',
        ]);
    }

    // ==========================================
    // TEST CORRECTION (SET STOCK)
    // ==========================================

    /** @test */
    public function correction_dapat_set_stok_ke_nilai_tertentu(): void
    {
        $this->actingAs($this->user);

        // Stock awal 100, set ke 75
        $result = $this->inventoryService->stockCorrection(
            $this->produk,
            75,
            'Koreksi setelah stock opname',
            $this->user->id
        );

        $newStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(75, $newStock);
    }

    /** @test */
    public function correction_dapat_menambah_stok(): void
    {
        $this->actingAs($this->user);

        // Stock awal 100, set ke 120
        $this->inventoryService->stockCorrection(
            $this->produk,
            120,
            'Koreksi stok naik'
        );

        $newStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(120, $newStock);
    }

    // ==========================================
    // TEST RELASI ADJUSTMENT KE STOCK MOVEMENT
    // ==========================================

    /** @test */
    public function adjustment_memiliki_relasi_ke_stock_movement(): void
    {
        $this->actingAs($this->user);

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            10,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Test relasi'
        );

        $adjustment = $result['adjustment'];
        $movement = $result['movement'];

        // Verifikasi referensi di stock_movements
        $this->assertEquals('adjustment', $movement->reference_type);
        $this->assertEquals($adjustment->id, $movement->reference_id);
        $this->assertEquals($adjustment->journal_number, $movement->journal_number);
    }

    // ==========================================
    // TEST SCOPE WITH JOURNAL
    // ==========================================

    /** @test */
    public function scope_with_journal_hanya_menampilkan_adjustment_dengan_jurnal(): void
    {
        $this->actingAs($this->user);

        // Buat adjustment dengan jurnal
        $this->inventoryService->createAdjustment(
            $this->produk,
            10,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Dengan jurnal'
        );

        // Buat adjustment tanpa jurnal (manual insert untuk test)
        InventoryAdjustment::create([
            'journal_number' => null,
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => 'adjustment_in',
            'quantity_change' => 5,
            'reason' => 'Tanpa jurnal',
        ]);

        $withJournal = InventoryAdjustment::withJournal()->count();
        $total = InventoryAdjustment::count();

        $this->assertEquals(1, $withJournal);
        $this->assertEquals(2, $total);
    }

    // ==========================================
    // TEST TYPE LABELS
    // ==========================================

    /** @test */
    public function adjustment_memiliki_label_tipe_indonesia(): void
    {
        $adjustment = new InventoryAdjustment(['type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN]);
        $this->assertEquals('Adjustment Masuk', $adjustment->type_label);

        $adjustment = new InventoryAdjustment(['type' => InventoryAdjustment::TYPE_ADJUSTMENT_OUT]);
        $this->assertEquals('Adjustment Keluar', $adjustment->type_label);

        $adjustment = new InventoryAdjustment(['type' => InventoryAdjustment::TYPE_RETURN]);
        $this->assertEquals('Return Barang', $adjustment->type_label);

        $adjustment = new InventoryAdjustment(['type' => InventoryAdjustment::TYPE_DAMAGE]);
        $this->assertEquals('Barang Rusak', $adjustment->type_label);

        $adjustment = new InventoryAdjustment(['type' => InventoryAdjustment::TYPE_CORRECTION]);
        $this->assertEquals('Koreksi Stok', $adjustment->type_label);
    }
}
