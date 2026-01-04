<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Pembelian (Purchase)
 *
 * Test ini memastikan alur pembelian/restock berjalan dengan benar:
 * - Relasi purchase dengan items
 * - Perhitungan total pembelian
 * - Update stok otomatis
 * - Status pembelian
 */
class PembelianTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $kategori;
    protected Product $produk1;
    protected Product $produk2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Manager Pembelian',
        ]);

        $this->kategori = Category::create([
            'name' => 'Snack',
            'description' => 'Makanan ringan',
        ]);

        $this->produk1 = Product::create([
            'barcode' => 'SNK001',
            'title' => 'Keripik Singkong',
            'description' => 'Keripik singkong renyah',
            'category_id' => $this->kategori->id,
            'buy_price' => 8000,
            'sell_price' => 12000,
            'stock' => 20,
        ]);

        $this->produk2 = Product::create([
            'barcode' => 'SNK002',
            'title' => 'Kacang Goreng',
            'description' => 'Kacang goreng gurih',
            'category_id' => $this->kategori->id,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 30,
        ]);

        // Buat inventory untuk produk
        Inventory::create([
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => $this->produk1->stock,
        ]);

        Inventory::create([
            'product_id' => $this->produk2->id,
            'barcode' => $this->produk2->barcode,
            'quantity' => $this->produk2->stock,
        ]);
    }

    // ==========================================
    // TEST RELASI PURCHASE
    // ==========================================

    /** @test */
    public function purchase_memiliki_banyak_items(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'CV Snack Enak',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => 50,
            'purchase_price' => 8000,
            'total_price' => 400000,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk2->id,
            'barcode' => $this->produk2->barcode,
            'quantity' => 30,
            'purchase_price' => 10000,
            'total_price' => 300000,
        ]);

        $purchase->refresh();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $purchase->items);
        $this->assertCount(2, $purchase->items);
    }

    /** @test */
    public function purchase_item_memiliki_relasi_ke_purchase(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier ABC',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => 25,
            'purchase_price' => 8000,
            'total_price' => 200000,
        ]);

        $this->assertInstanceOf(Purchase::class, $item->purchase);
        $this->assertEquals($purchase->id, $item->purchase->id);
    }

    /** @test */
    public function purchase_item_memiliki_relasi_ke_produk(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier XYZ',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => 40,
            'purchase_price' => 8000,
            'total_price' => 320000,
        ]);

        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertEquals($this->produk1->id, $item->product->id);
        $this->assertEquals('Keripik Singkong', $item->product->title);
    }

    // ==========================================
    // TEST PERHITUNGAN PEMBELIAN
    // ==========================================

    /** @test */
    public function perhitungan_total_harga_item_benar(): void
    {
        $jumlah = 50;
        $hargaSatuan = $this->produk1->buy_price; // 8000
        $totalHarga = $jumlah * $hargaSatuan; // 400000

        $purchase = Purchase::create([
            'supplier_name' => 'Supplier Hitung',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => $jumlah,
            'purchase_price' => $hargaSatuan,
            'total_price' => $totalHarga,
        ]);

        $this->assertEquals(50, $item->quantity);
        $this->assertEquals(8000, $item->purchase_price);
        $this->assertEquals(400000, $item->total_price);
        $this->assertEquals(
            $item->quantity * $item->purchase_price,
            $item->total_price
        );
    }

    /** @test */
    public function perhitungan_total_semua_item_purchase_benar(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Multi Item Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        // Item 1: 50 x 8000 = 400000
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => 50,
            'purchase_price' => 8000,
            'total_price' => 400000,
        ]);

        // Item 2: 30 x 10000 = 300000
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk2->id,
            'barcode' => $this->produk2->barcode,
            'quantity' => 30,
            'purchase_price' => 10000,
            'total_price' => 300000,
        ]);

        $purchase->refresh();

        // Total = 400000 + 300000 = 700000
        $totalPembelian = $purchase->items->sum('total_price');

        $this->assertEquals(700000, $totalPembelian);
    }

    // ==========================================
    // TEST STATUS PEMBELIAN
    // ==========================================

    /** @test */
    public function purchase_bisa_berstatus_pending(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Pending Supplier',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $purchase->status);
    }

    /** @test */
    public function purchase_bisa_berstatus_received(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Received Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        $this->assertEquals('received', $purchase->status);
    }

    /** @test */
    public function purchase_bisa_berstatus_cancelled(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Cancelled Supplier',
            'purchase_date' => now(),
            'status' => 'cancelled',
        ]);

        $this->assertEquals('cancelled', $purchase->status);
    }

    // ==========================================
    // TEST INTEGRASI DENGAN INVENTORY
    // ==========================================

    /** @test */
    public function purchase_received_menambah_stok_inventory(): void
    {
        $this->actingAs($this->user);

        $stokAwalProduk1 = $this->produk1->stock; // 20
        $stokAwalProduk2 = $this->produk2->stock; // 30
        $jumlahBeliProduk1 = 50;
        $jumlahBeliProduk2 = 40;

        $purchase = Purchase::create([
            'supplier_name' => 'Stock Update Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => $jumlahBeliProduk1,
            'purchase_price' => 8000,
            'total_price' => 400000,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk2->id,
            'barcode' => $this->produk2->barcode,
            'quantity' => $jumlahBeliProduk2,
            'purchase_price' => 10000,
            'total_price' => 400000,
        ]);

        $purchase->load('items.product');

        // Proses pembelian dengan inventory service
        $inventoryService = new InventoryService();
        $inventoryService->processPurchase($purchase);

        // Refresh data
        $this->produk1->refresh();
        $this->produk2->refresh();

        $inventoryProduk1 = Inventory::where('product_id', $this->produk1->id)->first();
        $inventoryProduk2 = Inventory::where('product_id', $this->produk2->id)->first();

        // Verifikasi stok bertambah
        $this->assertEquals($stokAwalProduk1 + $jumlahBeliProduk1, $inventoryProduk1->quantity); // 70
        $this->assertEquals($stokAwalProduk2 + $jumlahBeliProduk2, $inventoryProduk2->quantity); // 70
    }

    /** @test */
    public function purchase_membuat_adjustment_record(): void
    {
        $this->actingAs($this->user);

        $purchase = Purchase::create([
            'supplier_name' => 'Adjustment Record Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => 25,
            'purchase_price' => 8000,
            'total_price' => 200000,
        ]);

        $purchase->load('items.product');

        $inventoryService = new InventoryService();
        $inventoryService->processPurchase($purchase);

        // Cek adjustment tercatat
        $adjustment = InventoryAdjustment::where('product_id', $this->produk1->id)
            ->where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertEquals(InventoryAdjustment::TYPE_PURCHASE, $adjustment->type);
        $this->assertEquals(25, $adjustment->quantity_change);
        $this->assertStringContains('Adjustment Record Supplier', $adjustment->reason);
    }

    // ==========================================
    // TEST DATA SUPPLIER
    // ==========================================

    /** @test */
    public function purchase_menyimpan_nama_supplier(): void
    {
        $namaSupplier = 'PT Sumber Rezeki Makmur';

        $purchase = Purchase::create([
            'supplier_name' => $namaSupplier,
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        $this->assertEquals($namaSupplier, $purchase->supplier_name);
    }

    /** @test */
    public function purchase_menyimpan_tanggal_pembelian(): void
    {
        $tanggal = now()->subDays(3);

        $purchase = Purchase::create([
            'supplier_name' => 'Date Test Supplier',
            'purchase_date' => $tanggal,
            'status' => 'received',
        ]);

        $this->assertEquals(
            $tanggal->toDateString(),
            $purchase->purchase_date instanceof \Carbon\Carbon
                ? $purchase->purchase_date->toDateString()
                : \Carbon\Carbon::parse($purchase->purchase_date)->toDateString()
        );
    }

    /** @test */
    public function purchase_bisa_menyimpan_catatan(): void
    {
        $catatan = 'Barang dikirim via ekspedisi JNE, estimasi 3 hari kerja';

        $purchase = Purchase::create([
            'supplier_name' => 'Notes Test Supplier',
            'purchase_date' => now(),
            'status' => 'pending',
            'notes' => $catatan,
        ]);

        $this->assertEquals($catatan, $purchase->notes);
    }

    /** @test */
    public function purchase_bisa_menyimpan_reference(): void
    {
        $reference = 'PO-2026-001';

        $purchase = Purchase::create([
            'supplier_name' => 'Reference Test Supplier',
            'purchase_date' => now(),
            'status' => 'received',
            'reference' => $reference,
        ]);

        $this->assertEquals($reference, $purchase->reference);
    }

    // ==========================================
    // TEST SKENARIO BISNIS
    // ==========================================

    /** @test */
    public function skenario_restock_produk_habis(): void
    {
        $this->actingAs($this->user);

        // Set stok produk ke 0
        $this->produk1->update(['stock' => 0]);
        $inventory = Inventory::where('product_id', $this->produk1->id)->first();
        $inventory->update(['quantity' => 0]);

        $this->assertEquals(0, $this->produk1->fresh()->stock);
        $this->assertEquals(0, $inventory->fresh()->quantity);

        // Lakukan pembelian untuk restock
        $jumlahRestock = 100;

        $purchase = Purchase::create([
            'supplier_name' => 'Emergency Restock Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => $jumlahRestock,
            'purchase_price' => 8000,
            'total_price' => 800000,
        ]);

        $purchase->load('items.product');

        $inventoryService = new InventoryService();
        $inventoryService->processPurchase($purchase);

        // Verifikasi stok sudah terisi kembali
        $this->produk1->refresh();
        $inventory->refresh();

        $this->assertEquals($jumlahRestock, $this->produk1->stock);
        $this->assertEquals($jumlahRestock, $inventory->quantity);
    }

    /** @test */
    public function skenario_pembelian_multi_produk(): void
    {
        $this->actingAs($this->user);

        $stokAwal1 = $this->produk1->stock;
        $stokAwal2 = $this->produk2->stock;

        $purchase = Purchase::create([
            'supplier_name' => 'Bulk Order Supplier',
            'purchase_date' => now(),
            'status' => 'received',
            'notes' => 'Order bulk untuk stok bulanan',
        ]);

        // Order 3 produk berbeda dalam 1 PO
        $items = [
            ['product' => $this->produk1, 'qty' => 100],
            ['product' => $this->produk2, 'qty' => 75],
        ];

        foreach ($items as $itemData) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $itemData['product']->id,
                'barcode' => $itemData['product']->barcode,
                'quantity' => $itemData['qty'],
                'purchase_price' => $itemData['product']->buy_price,
                'total_price' => $itemData['product']->buy_price * $itemData['qty'],
            ]);
        }

        $purchase->load('items.product');

        $inventoryService = new InventoryService();
        $inventoryService->processPurchase($purchase);

        // Verifikasi semua produk ter-update
        $this->produk1->refresh();
        $this->produk2->refresh();

        $inv1 = Inventory::where('product_id', $this->produk1->id)->first();
        $inv2 = Inventory::where('product_id', $this->produk2->id)->first();

        $this->assertEquals($stokAwal1 + 100, $inv1->quantity);
        $this->assertEquals($stokAwal2 + 75, $inv2->quantity);

        // Verifikasi jumlah adjustment = jumlah item
        $adjustmentCount = InventoryAdjustment::where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->count();

        $this->assertEquals(2, $adjustmentCount);
    }

    /**
     * Custom assertion untuk string contains
     */
    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
