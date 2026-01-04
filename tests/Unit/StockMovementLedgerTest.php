<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test StockMovement Ledger
 *
 * Test ini memastikan sistem pencatatan pergerakan stok berfungsi dengan benar:
 * - Pencatatan pembelian (purchase) ke stock ledger
 * - Pencatatan penjualan (sale) ke stock ledger
 * - Pencatatan adjustment manual dengan nomor jurnal
 * - Perhitungan average buy price dari pembelian
 * - Perhitungan saldo stok dari ledger
 */
class StockMovementLedgerTest extends TestCase
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
            'name' => 'Elektronik',
            'description' => 'Kategori barang elektronik',
        ]);

        $this->produk = Product::create([
            'barcode' => 'ELEC001',
            'title' => 'Charger HP',
            'description' => 'Charger handphone universal',
            'category_id' => $this->kategori->id,
            'sell_price' => 50000,
            'stock' => 0, // Stok awal 0
        ]);
    }

    // ==========================================
    // TEST PENCATATAN STOCK MOVEMENT
    // ==========================================

    /** @test */
    public function dapat_mencatat_stock_movement_masuk(): void
    {
        $this->actingAs($this->user);

        $movement = StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 100,
            'notes' => 'Pembelian pertama',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->produk->id,
            'movement_type' => 'purchase',
            'quantity' => 100,
            'total_price' => 2500000,
        ]);
    }

    /** @test */
    public function dapat_mencatat_stock_movement_keluar(): void
    {
        $this->actingAs($this->user);

        // Tambah stok dulu
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Catat penjualan
        $movement = StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'reference_type' => 'transaction',
            'reference_id' => 1,
            'quantity' => -10, // Negatif untuk keluar
            'unit_price' => 50000,
            'total_price' => 500000,
            'quantity_before' => 100,
            'quantity_after' => 90,
            'notes' => 'Penjualan invoice TRX-001',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->produk->id,
            'movement_type' => 'sale',
            'quantity' => -10,
        ]);
    }

    // ==========================================
    // TEST PERHITUNGAN AVERAGE BUY PRICE
    // ==========================================

    /** @test */
    public function dapat_menghitung_average_buy_price_dari_satu_pembelian(): void
    {
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        $averagePrice = StockMovement::getAverageBuyPrice($this->produk->id);

        $this->assertEquals(25000, $averagePrice);
    }

    /** @test */
    public function dapat_menghitung_average_buy_price_dari_beberapa_pembelian(): void
    {
        // Pembelian pertama: 100 unit @ Rp 25.000 = Rp 2.500.000
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Pembelian kedua: 50 unit @ Rp 30.000 = Rp 1.500.000
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 30000,
            'total_price' => 1500000,
            'quantity_before' => 100,
            'quantity_after' => 150,
        ]);

        // Total: 150 unit, Total harga: Rp 4.000.000
        // Average: 4.000.000 / 150 = 26.666,67
        $averagePrice = StockMovement::getAverageBuyPrice($this->produk->id);

        $this->assertEquals(26666.67, $averagePrice);
    }

    /** @test */
    public function average_buy_price_nol_jika_tidak_ada_pembelian(): void
    {
        $averagePrice = StockMovement::getAverageBuyPrice($this->produk->id);

        $this->assertEquals(0, $averagePrice);
    }

    /** @test */
    public function product_model_mengembalikan_average_buy_price(): void
    {
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        $this->produk->refresh();

        $this->assertEquals(25000, $this->produk->average_buy_price);
        $this->assertEquals(25000, $this->produk->buy_price);
    }

    // ==========================================
    // TEST PERHITUNGAN SALDO STOK
    // ==========================================

    /** @test */
    public function dapat_menghitung_saldo_stok_dari_ledger(): void
    {
        // Pembelian: +100
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Penjualan: -30
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -30,
            'unit_price' => 50000,
            'total_price' => 1500000,
            'quantity_before' => 100,
            'quantity_after' => 70,
        ]);

        $currentStock = StockMovement::getCurrentStock($this->produk->id);

        $this->assertEquals(70, $currentStock);
    }

    /** @test */
    public function saldo_stok_nol_jika_tidak_ada_movement(): void
    {
        $currentStock = StockMovement::getCurrentStock($this->produk->id);

        $this->assertEquals(0, $currentStock);
    }

    // ==========================================
    // TEST INVENTORY ADJUSTMENT DENGAN NOMOR JURNAL
    // ==========================================

    /** @test */
    public function dapat_membuat_adjustment_dengan_nomor_jurnal(): void
    {
        $this->actingAs($this->user);

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            50,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Penyesuaian stok awal',
            'Catatan tambahan',
            $this->user->id
        );

        $this->assertNotNull($result['adjustment']->journal_number);
        $this->assertStringStartsWith('ADJ', $result['adjustment']->journal_number);

        $this->assertDatabaseHas('inventory_adjustments', [
            'product_id' => $this->produk->id,
            'type' => 'adjustment_in',
            'quantity_change' => 50,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->produk->id,
            'movement_type' => 'adjustment_in',
            'quantity' => 50,
        ]);
    }

    /** @test */
    public function nomor_jurnal_unik_per_hari(): void
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
            20,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Adjustment 2'
        );

        $journal1 = $result1['adjustment']->journal_number;
        $journal2 = $result2['adjustment']->journal_number;

        $this->assertNotEquals($journal1, $journal2);

        // Format: ADJ + YYYYMMDD + 4 digit sequence
        $this->assertMatchesRegularExpression('/^ADJ\d{8}\d{4}$/', $journal1);
        $this->assertMatchesRegularExpression('/^ADJ\d{8}\d{4}$/', $journal2);
    }

    /** @test */
    public function adjustment_keluar_mengurangi_stok(): void
    {
        $this->actingAs($this->user);

        // Tambah stok dulu
        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 25000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        $this->produk->stock = 100;
        $this->produk->save();

        // Adjustment keluar
        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            30,
            InventoryAdjustment::TYPE_ADJUSTMENT_OUT,
            'Barang hilang'
        );

        $currentStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(70, $currentStock);

        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => 'adjustment_out',
            'quantity' => -30,
        ]);
    }

    // ==========================================
    // TEST SCOPES STOCK MOVEMENT
    // ==========================================

    /** @test */
    public function dapat_filter_movement_berdasarkan_tipe(): void
    {
        // Buat beberapa movement dengan tipe berbeda
        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -10,
            'quantity_before' => 100,
            'quantity_after' => 90,
        ]);

        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_ADJUSTMENT_IN,
            'quantity' => 5,
            'quantity_before' => 90,
            'quantity_after' => 95,
        ]);

        $purchaseCount = StockMovement::ofType(StockMovement::TYPE_PURCHASE)->count();
        $saleCount = StockMovement::ofType(StockMovement::TYPE_SALE)->count();
        $incomingCount = StockMovement::incoming()->count();
        $outgoingCount = StockMovement::outgoing()->count();

        $this->assertEquals(1, $purchaseCount);
        $this->assertEquals(1, $saleCount);
        $this->assertEquals(2, $incomingCount); // purchase + adjustment_in
        $this->assertEquals(1, $outgoingCount); // sale
    }

    /** @test */
    public function dapat_filter_movement_berdasarkan_produk(): void
    {
        $produk2 = Product::create([
            'barcode' => 'ELEC002',
            'title' => 'Kabel USB',
            'description' => 'Kabel USB tipe C',
            'category_id' => $this->kategori->id,
            'sell_price' => 30000,
            'stock' => 0,
        ]);

        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        StockMovement::create([
            'product_id' => $produk2->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'quantity_before' => 0,
            'quantity_after' => 50,
        ]);

        $produk1Movements = StockMovement::forProduct($this->produk->id)->count();
        $produk2Movements = StockMovement::forProduct($produk2->id)->count();

        $this->assertEquals(1, $produk1Movements);
        $this->assertEquals(1, $produk2Movements);
    }

    // ==========================================
    // TEST TYPE LABELS
    // ==========================================

    /** @test */
    public function movement_memiliki_label_tipe_yang_benar(): void
    {
        $movement = new StockMovement(['movement_type' => StockMovement::TYPE_PURCHASE]);
        $this->assertEquals('Pembelian', $movement->type_label);

        $movement = new StockMovement(['movement_type' => StockMovement::TYPE_SALE]);
        $this->assertEquals('Penjualan', $movement->type_label);

        $movement = new StockMovement(['movement_type' => StockMovement::TYPE_ADJUSTMENT_IN]);
        $this->assertEquals('Adjustment Masuk', $movement->type_label);

        $movement = new StockMovement(['movement_type' => StockMovement::TYPE_ADJUSTMENT_OUT]);
        $this->assertEquals('Adjustment Keluar', $movement->type_label);
    }

    /** @test */
    public function dapat_cek_apakah_movement_incoming_atau_outgoing(): void
    {
        $purchaseMovement = new StockMovement(['movement_type' => StockMovement::TYPE_PURCHASE]);
        $saleMovement = new StockMovement(['movement_type' => StockMovement::TYPE_SALE]);

        $this->assertTrue($purchaseMovement->isIncoming());
        $this->assertFalse($purchaseMovement->isOutgoing());

        $this->assertFalse($saleMovement->isIncoming());
        $this->assertTrue($saleMovement->isOutgoing());
    }
}
