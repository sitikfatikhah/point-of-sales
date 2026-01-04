<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Inventory dan Stok
 *
 * Test ini memastikan manajemen stok berjalan dengan benar
 * menggunakan arsitektur baru dengan StockMovement sebagai ledger utama
 */
class StokInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected User $user;
    protected Category $kategori;
    protected Product $produk;
    protected Inventory $inventory;

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
            'title' => 'Es Teh Manis',
            'description' => 'Es teh manis segar',
            'category_id' => $this->kategori->id,
            'buy_price' => 3000,
            'sell_price' => 5000,
            'stock' => 50,
        ]);

        $this->inventory = Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $this->produk->stock,
        ]);

        // Create initial stock movement
        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 3000,
            'total_price' => 150000,
            'quantity_before' => 0,
            'quantity_after' => 50,
        ]);
    }

    // ==========================================
    // TEST RELASI INVENTORY
    // ==========================================

    /** @test */
    public function inventory_memiliki_relasi_ke_produk(): void
    {
        $this->assertInstanceOf(Product::class, $this->inventory->product);
        $this->assertEquals($this->produk->id, $this->inventory->product->id);
        $this->assertEquals('Es Teh Manis', $this->inventory->product->title);
    }

    /** @test */
    public function produk_memiliki_relasi_ke_inventory(): void
    {
        $this->assertInstanceOf(Inventory::class, $this->produk->inventory);
        $this->assertEquals($this->inventory->id, $this->produk->inventory->id);
    }

    /** @test */
    public function inventory_memiliki_relasi_ke_stock_movements(): void
    {
        $this->inventory->refresh();
        $this->assertCount(1, $this->inventory->stockMovements);
    }

    // ==========================================
    // TEST PENAMBAHAN STOK
    // ==========================================

    /** @test */
    public function dapat_menambah_stok_melalui_adjustment(): void
    {
        $this->actingAs($this->user);

        $stokAwal = StockMovement::getCurrentStock($this->produk->id); // 50
        $jumlahTambah = 25;

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            $jumlahTambah,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Penambahan stok manual',
            null,
            $this->user->id
        );

        $stokAkhir = StockMovement::getCurrentStock($this->produk->id);

        // Verifikasi stok bertambah
        $this->assertEquals($stokAwal + $jumlahTambah, $stokAkhir);

        // Verifikasi adjustment tercatat dengan benar
        $this->assertEquals(InventoryAdjustment::TYPE_ADJUSTMENT_IN, $result['adjustment']->type);
        $this->assertEquals($jumlahTambah, $result['adjustment']->quantity_change);
        $this->assertNotNull($result['adjustment']->journal_number);
    }

    /** @test */
    public function dapat_menambah_stok_melalui_purchase(): void
    {
        $this->actingAs($this->user);

        $stokAwal = StockMovement::getCurrentStock($this->produk->id); // 50
        $jumlahBeli = 100;

        // Buat purchase order
        $purchase = Purchase::create([
            'supplier_name' => 'PT Teh Botol',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $jumlahBeli,
            'purchase_price' => 3000,
            'total_price' => 300000,
        ]);

        $purchase->load('items.product');

        // Proses pembelian
        $this->inventoryService->processPurchase($purchase);

        $stokAkhir = StockMovement::getCurrentStock($this->produk->id);

        // Verifikasi stok bertambah sesuai jumlah pembelian
        $this->assertEquals($stokAwal + $jumlahBeli, $stokAkhir);

        // Verifikasi stock movement tercatat sebagai purchase
        $movement = StockMovement::where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(StockMovement::TYPE_PURCHASE, $movement->movement_type);
        $this->assertEquals($jumlahBeli, $movement->quantity);
    }

    // ==========================================
    // TEST PENGURANGAN STOK
    // ==========================================

    /** @test */
    public function dapat_mengurangi_stok_melalui_adjustment(): void
    {
        $this->actingAs($this->user);

        $stokAwal = StockMovement::getCurrentStock($this->produk->id); // 50
        $jumlahKurang = 10;

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            $jumlahKurang,
            InventoryAdjustment::TYPE_ADJUSTMENT_OUT,
            'Pengurangan stok manual',
            null,
            $this->user->id
        );

        $stokAkhir = StockMovement::getCurrentStock($this->produk->id);

        // Verifikasi stok berkurang
        $this->assertEquals($stokAwal - $jumlahKurang, $stokAkhir);

        // Verifikasi adjustment tercatat dengan benar
        $this->assertEquals(InventoryAdjustment::TYPE_ADJUSTMENT_OUT, $result['adjustment']->type);
        $this->assertEquals(-$jumlahKurang, $result['adjustment']->quantity_change);
    }

    /** @test */
    public function dapat_melakukan_damage_adjustment(): void
    {
        $this->actingAs($this->user);

        $stokAwal = StockMovement::getCurrentStock($this->produk->id); // 50
        $jumlahRusak = 5;

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            $jumlahRusak,
            InventoryAdjustment::TYPE_DAMAGE,
            'Barang rusak/expired',
            null,
            $this->user->id
        );

        $stokAkhir = StockMovement::getCurrentStock($this->produk->id);

        $this->assertEquals($stokAwal - $jumlahRusak, $stokAkhir);
        $this->assertEquals(InventoryAdjustment::TYPE_DAMAGE, $result['adjustment']->type);
    }

    // ==========================================
    // TEST KOREKSI STOK
    // ==========================================

    /** @test */
    public function dapat_melakukan_koreksi_stok_ke_nilai_tertentu(): void
    {
        $this->actingAs($this->user);

        $stokBaru = 75;

        $result = $this->inventoryService->stockCorrection(
            $this->produk,
            $stokBaru,
            'Hasil stock opname fisik',
            $this->user->id
        );

        $stokAkhir = StockMovement::getCurrentStock($this->produk->id);

        // Verifikasi stok sudah diset ke nilai baru
        $this->assertEquals($stokBaru, $stokAkhir);

        // Verifikasi adjustment dan movement dibuat
        $this->assertNotNull($result['adjustment']);
        $this->assertNotNull($result['movement']);
    }

    /** @test */
    public function koreksi_stok_bisa_menambah_atau_mengurangi(): void
    {
        $this->actingAs($this->user);

        // Koreksi naik
        $this->inventoryService->stockCorrection($this->produk, 80, 'Naik', $this->user->id);
        $this->assertEquals(80, StockMovement::getCurrentStock($this->produk->id));

        // Koreksi turun
        $this->inventoryService->stockCorrection($this->produk, 60, 'Turun', $this->user->id);
        $this->assertEquals(60, StockMovement::getCurrentStock($this->produk->id));
    }

    // ==========================================
    // TEST RETURN
    // ==========================================

    /** @test */
    public function dapat_melakukan_return_adjustment(): void
    {
        $this->actingAs($this->user);

        $stokAwal = StockMovement::getCurrentStock($this->produk->id);
        $jumlahReturn = 5;

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            $jumlahReturn,
            InventoryAdjustment::TYPE_RETURN,
            'Return dari pelanggan',
            null,
            $this->user->id
        );

        $stokAkhir = StockMovement::getCurrentStock($this->produk->id);

        // Return menambah stok
        $this->assertEquals($stokAwal + $jumlahReturn, $stokAkhir);
        $this->assertEquals(InventoryAdjustment::TYPE_RETURN, $result['adjustment']->type);
    }

    // ==========================================
    // TEST PENCATATAN RIWAYAT
    // ==========================================

    /** @test */
    public function setiap_perubahan_stok_tercatat_di_stock_movement(): void
    {
        $this->actingAs($this->user);

        // Lakukan beberapa operasi stok
        $this->inventoryService->createAdjustment(
            $this->produk,
            10,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Tambah stok'
        );

        $this->inventoryService->createAdjustment(
            $this->produk,
            5,
            InventoryAdjustment::TYPE_DAMAGE,
            'Barang rusak'
        );

        // 1 dari setUp + 2 dari test
        $movements = StockMovement::forProduct($this->produk->id)->count();
        $this->assertEquals(3, $movements);
    }

    /** @test */
    public function stock_movement_mencatat_user_yang_melakukan(): void
    {
        $this->actingAs($this->user);

        $result = $this->inventoryService->createAdjustment(
            $this->produk,
            15,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Test user tracking',
            null,
            $this->user->id
        );

        $this->assertEquals($this->user->id, $result['movement']->user_id);
        $this->assertInstanceOf(User::class, $result['movement']->user);
    }

    // ==========================================
    // TEST PEMBATALAN PEMBELIAN
    // ==========================================

    /** @test */
    public function dapat_membatalkan_pembelian_dan_mengembalikan_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = StockMovement::getCurrentStock($this->produk->id); // 50
        $jumlahBeli = 30;

        // Buat dan proses pembelian
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier Test',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $jumlahBeli,
            'purchase_price' => 3000,
            'total_price' => 90000,
        ]);

        $purchase->load('items.product');
        $this->inventoryService->processPurchase($purchase);

        $stokSetelahBeli = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals($stokAwal + $jumlahBeli, $stokSetelahBeli); // 80

        // Batalkan pembelian
        $this->inventoryService->reversePurchase($purchase);

        $stokSetelahBatal = StockMovement::getCurrentStock($this->produk->id);

        // Stok kembali ke semula
        $this->assertEquals($stokAwal, $stokSetelahBatal); // 50
    }

    // ==========================================
    // TEST SINKRONISASI INVENTORY
    // ==========================================

    /** @test */
    public function dapat_sinkronisasi_inventory_dengan_produk(): void
    {
        // Buat produk baru tanpa inventory
        $produkBaru = Product::create([
            'barcode' => 'MNM002',
            'title' => 'Kopi Hitam',
            'description' => 'Kopi hitam murni',
            'category_id' => $this->kategori->id,
            'buy_price' => 5000,
            'sell_price' => 8000,
            'stock' => 25,
        ]);

        // Pastikan belum ada inventory
        $this->assertNull($produkBaru->inventory);

        // Sinkronisasi
        $synced = $this->inventoryService->syncInventoryWithProducts();

        $produkBaru->refresh();

        // Sekarang inventory sudah ada
        $this->assertNotNull($produkBaru->inventory);
        $this->assertEquals($produkBaru->stock, $produkBaru->inventory->quantity);
    }

    // ==========================================
    // TEST TIPE ADJUSTMENT
    // ==========================================

    /** @test */
    public function semua_tipe_adjustment_tersedia(): void
    {
        $tipeYangDiharapkan = [
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            InventoryAdjustment::TYPE_ADJUSTMENT_OUT,
            InventoryAdjustment::TYPE_RETURN,
            InventoryAdjustment::TYPE_DAMAGE,
            InventoryAdjustment::TYPE_CORRECTION,
        ];

        $tipeYangAda = InventoryAdjustment::getTypes();

        foreach ($tipeYangDiharapkan as $tipe) {
            $this->assertContains($tipe, $tipeYangAda);
        }
    }

    /** @test */
    public function incoming_types_menambah_stok(): void
    {
        $incomingTypes = InventoryAdjustment::getIncomingTypes();

        $this->assertContains(InventoryAdjustment::TYPE_ADJUSTMENT_IN, $incomingTypes);
        $this->assertContains(InventoryAdjustment::TYPE_RETURN, $incomingTypes);
    }

    /** @test */
    public function outgoing_types_mengurangi_stok(): void
    {
        $outgoingTypes = InventoryAdjustment::getOutgoingTypes();

        $this->assertContains(InventoryAdjustment::TYPE_ADJUSTMENT_OUT, $outgoingTypes);
        $this->assertContains(InventoryAdjustment::TYPE_DAMAGE, $outgoingTypes);
    }

    // ==========================================
    // TEST AVERAGE BUY PRICE
    // ==========================================

    /** @test */
    public function average_buy_price_dihitung_dari_stock_movements(): void
    {
        // Tambah pembelian lagi dengan harga berbeda
        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 4000, // Harga berbeda
            'total_price' => 200000,
            'quantity_before' => 50,
            'quantity_after' => 100,
        ]);

        // Average = (150000 + 200000) / (50 + 50) = 350000 / 100 = 3500
        $avgPrice = StockMovement::getAverageBuyPrice($this->produk->id);

        $this->assertEquals(3500, $avgPrice);
    }

    /** @test */
    public function product_model_mengembalikan_average_buy_price(): void
    {
        // Product accessor should return average from StockMovement
        $avgPrice = $this->produk->average_buy_price;

        $this->assertEquals(3000, $avgPrice); // From initial stock movement
    }
}
