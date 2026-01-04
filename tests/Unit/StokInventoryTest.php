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
 * Unit Test Inventory dan Stok
 *
 * Test ini memastikan manajemen stok berjalan dengan benar:
 * - Sinkronisasi stok produk dengan inventory
 * - Penambahan stok melalui pembelian
 * - Pengurangan stok melalui penjualan
 * - Adjustment manual stok
 * - Pencatatan riwayat perubahan stok
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
    public function inventory_memiliki_banyak_adjustment(): void
    {
        $this->actingAs($this->user);

        // Buat beberapa adjustment
        InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => InventoryAdjustment::TYPE_IN,
            'quantity_before' => 50,
            'quantity_change' => 10,
            'quantity_after' => 60,
            'reason' => 'Restock',
        ]);

        InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => InventoryAdjustment::TYPE_OUT,
            'quantity_before' => 60,
            'quantity_change' => -5,
            'quantity_after' => 55,
            'reason' => 'Penjualan',
        ]);

        $this->inventory->refresh();

        $this->assertCount(2, $this->inventory->adjustments);
    }

    // ==========================================
    // TEST PENAMBAHAN STOK
    // ==========================================

    /** @test */
    public function dapat_menambah_stok_melalui_inventory_add_stock(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity; // 50
        $jumlahTambah = 25;

        $adjustment = $this->inventory->addStock(
            quantity: $jumlahTambah,
            type: InventoryAdjustment::TYPE_IN,
            reason: 'Penambahan stok manual',
            referenceType: null,
            referenceId: null,
            userId: $this->user->id
        );

        $this->inventory->refresh();
        $this->produk->refresh();

        // Verifikasi stok inventory bertambah
        $this->assertEquals($stokAwal + $jumlahTambah, $this->inventory->quantity);

        // Verifikasi stok produk juga bertambah
        $this->assertEquals($stokAwal + $jumlahTambah, $this->produk->stock);

        // Verifikasi adjustment tercatat dengan benar
        $this->assertEquals(InventoryAdjustment::TYPE_IN, $adjustment->type);
        $this->assertEquals($stokAwal, $adjustment->quantity_before);
        $this->assertEquals($jumlahTambah, $adjustment->quantity_change);
        $this->assertEquals($stokAwal + $jumlahTambah, $adjustment->quantity_after);
    }

    /** @test */
    public function dapat_menambah_stok_melalui_purchase(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity; // 50
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

        $this->inventory->refresh();
        $this->produk->refresh();

        // Verifikasi stok bertambah sesuai jumlah pembelian
        $this->assertEquals($stokAwal + $jumlahBeli, $this->inventory->quantity);
        $this->assertEquals($stokAwal + $jumlahBeli, $this->produk->stock);

        // Verifikasi adjustment tercatat sebagai purchase
        $adjustment = InventoryAdjustment::where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertEquals(InventoryAdjustment::TYPE_PURCHASE, $adjustment->type);
        $this->assertEquals($jumlahBeli, $adjustment->quantity_change);
    }

    // ==========================================
    // TEST PENGURANGAN STOK
    // ==========================================

    /** @test */
    public function dapat_mengurangi_stok_melalui_inventory_reduce_stock(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity; // 50
        $jumlahKurang = 10;

        $adjustment = $this->inventory->reduceStock(
            quantity: $jumlahKurang,
            type: InventoryAdjustment::TYPE_OUT,
            reason: 'Penjualan langsung',
            referenceType: null,
            referenceId: null,
            userId: $this->user->id
        );

        $this->inventory->refresh();
        $this->produk->refresh();

        // Verifikasi stok inventory berkurang
        $this->assertEquals($stokAwal - $jumlahKurang, $this->inventory->quantity);

        // Verifikasi stok produk juga berkurang
        $this->assertEquals($stokAwal - $jumlahKurang, $this->produk->stock);

        // Verifikasi adjustment tercatat dengan benar
        $this->assertEquals(InventoryAdjustment::TYPE_OUT, $adjustment->type);
        $this->assertEquals(-$jumlahKurang, $adjustment->quantity_change);
    }

    /** @test */
    public function stok_tidak_bisa_menjadi_negatif(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity; // 50
        $jumlahKurangBesar = 100; // Lebih besar dari stok

        $this->inventory->reduceStock(
            quantity: $jumlahKurangBesar,
            type: InventoryAdjustment::TYPE_OUT,
            reason: 'Test pengurangan besar',
            userId: $this->user->id
        );

        $this->inventory->refresh();

        // Stok tidak boleh negatif, minimum 0
        $this->assertEquals(0, $this->inventory->quantity);
        $this->assertGreaterThanOrEqual(0, $this->inventory->quantity);
    }

    // ==========================================
    // TEST ADJUSTMENT MANUAL
    // ==========================================

    /** @test */
    public function dapat_melakukan_adjustment_penambahan_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->produk->stock; // 50
        $jumlahTambah = 30;

        $adjustment = $this->inventoryService->manualAdjustment(
            product: $this->produk,
            quantity: $jumlahTambah,
            type: InventoryAdjustment::TYPE_IN,
            reason: 'Penambahan stok setelah stock opname',
            userId: $this->user->id
        );

        $this->inventory->refresh();
        $this->produk->refresh();

        $this->assertEquals($stokAwal + $jumlahTambah, $this->inventory->quantity);
        $this->assertEquals(InventoryAdjustment::TYPE_IN, $adjustment->type);
        $this->assertEquals($jumlahTambah, $adjustment->quantity_change);
    }

    /** @test */
    public function dapat_melakukan_adjustment_pengurangan_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->produk->stock; // 50
        $jumlahKurang = 5;

        $adjustment = $this->inventoryService->manualAdjustment(
            product: $this->produk,
            quantity: $jumlahKurang,
            type: InventoryAdjustment::TYPE_DAMAGE,
            reason: 'Barang rusak/expired',
            userId: $this->user->id
        );

        $this->inventory->refresh();
        $this->produk->refresh();

        $this->assertEquals($stokAwal - $jumlahKurang, $this->inventory->quantity);
        $this->assertEquals(InventoryAdjustment::TYPE_DAMAGE, $adjustment->type);
        $this->assertEquals(-$jumlahKurang, $adjustment->quantity_change);
    }

    /** @test */
    public function dapat_melakukan_koreksi_stok_ke_nilai_tertentu(): void
    {
        $this->actingAs($this->user);

        $stokBaru = 75;

        $adjustment = $this->inventoryService->stockCorrection(
            product: $this->produk,
            newQuantity: $stokBaru,
            reason: 'Hasil stock opname fisik',
            userId: $this->user->id
        );

        $this->inventory->refresh();
        $this->produk->refresh();

        // Verifikasi stok sudah diset ke nilai baru
        $this->assertEquals($stokBaru, $this->inventory->quantity);
        $this->assertEquals(InventoryAdjustment::TYPE_CORRECTION, $adjustment->type);
    }

    // ==========================================
    // TEST PENCATATAN RIWAYAT
    // ==========================================

    /** @test */
    public function setiap_perubahan_stok_tercatat_di_adjustment(): void
    {
        $this->actingAs($this->user);

        // Lakukan beberapa operasi stok
        $this->inventory->addStock(10, InventoryAdjustment::TYPE_IN, 'Restock', null, null, $this->user->id);
        $this->inventory->reduceStock(5, InventoryAdjustment::TYPE_OUT, 'Penjualan', null, null, $this->user->id);
        $this->inventory->addStock(20, InventoryAdjustment::TYPE_PURCHASE, 'Pembelian', 'purchase', 1, $this->user->id);

        $adjustments = InventoryAdjustment::where('product_id', $this->produk->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->assertCount(3, $adjustments);

        // Verifikasi urutan dan tipe adjustment
        $this->assertEquals(InventoryAdjustment::TYPE_IN, $adjustments[0]->type);
        $this->assertEquals(InventoryAdjustment::TYPE_OUT, $adjustments[1]->type);
        $this->assertEquals(InventoryAdjustment::TYPE_PURCHASE, $adjustments[2]->type);
    }

    /** @test */
    public function adjustment_mencatat_user_yang_melakukan(): void
    {
        $this->actingAs($this->user);

        $adjustment = $this->inventory->addStock(
            quantity: 15,
            type: InventoryAdjustment::TYPE_IN,
            reason: 'Test user tracking',
            userId: $this->user->id
        );

        $this->assertEquals($this->user->id, $adjustment->user_id);
        $this->assertInstanceOf(User::class, $adjustment->user);
        $this->assertEquals('Admin Gudang', $adjustment->user->name);
    }

    /** @test */
    public function adjustment_mencatat_referensi_transaksi(): void
    {
        $this->actingAs($this->user);

        $purchaseId = 999;

        $adjustment = $this->inventory->addStock(
            quantity: 50,
            type: InventoryAdjustment::TYPE_PURCHASE,
            reason: 'Pembelian dari supplier',
            referenceType: 'purchase',
            referenceId: $purchaseId,
            userId: $this->user->id
        );

        $this->assertEquals('purchase', $adjustment->reference_type);
        $this->assertEquals($purchaseId, $adjustment->reference_id);
    }

    // ==========================================
    // TEST REVERSE/PEMBATALAN
    // ==========================================

    /** @test */
    public function dapat_membatalkan_pembelian_dan_mengembalikan_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity; // 50
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

        $this->inventory->refresh();
        $this->assertEquals($stokAwal + $jumlahBeli, $this->inventory->quantity); // 80

        // Batalkan pembelian
        $this->inventoryService->reversePurchase($purchase);

        $this->inventory->refresh();

        // Stok kembali ke semula
        $this->assertEquals($stokAwal, $this->inventory->quantity); // 50
    }

    // ==========================================
    // TEST SINKRONISASI STOK
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
        $this->assertEquals($produkBaru->barcode, $produkBaru->inventory->barcode);
    }

    // ==========================================
    // TEST TIPE ADJUSTMENT
    // ==========================================

    /** @test */
    public function semua_tipe_adjustment_tersedia(): void
    {
        $tipeYangDiharapkan = [
            'in',
            'out',
            'adjustment',
            'purchase',
            'sale',
            'return',
            'damage',
            'correction',
        ];

        $tipeYangAda = InventoryAdjustment::getTypes();

        foreach ($tipeYangDiharapkan as $tipe) {
            $this->assertContains($tipe, $tipeYangAda);
        }
    }

    /** @test */
    public function adjustment_purchase_menambah_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity;

        $this->inventory->addStock(
            quantity: 10,
            type: InventoryAdjustment::TYPE_PURCHASE,
            reason: 'Pembelian',
            userId: $this->user->id
        );

        $this->inventory->refresh();

        $this->assertEquals($stokAwal + 10, $this->inventory->quantity);
    }

    /** @test */
    public function adjustment_sale_mengurangi_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity;

        $this->inventory->reduceStock(
            quantity: 5,
            type: InventoryAdjustment::TYPE_SALE,
            reason: 'Penjualan',
            userId: $this->user->id
        );

        $this->inventory->refresh();

        $this->assertEquals($stokAwal - 5, $this->inventory->quantity);
    }

    /** @test */
    public function adjustment_damage_mengurangi_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity;

        $this->inventory->reduceStock(
            quantity: 3,
            type: InventoryAdjustment::TYPE_DAMAGE,
            reason: 'Barang rusak',
            userId: $this->user->id
        );

        $this->inventory->refresh();

        $this->assertEquals($stokAwal - 3, $this->inventory->quantity);
    }

    /** @test */
    public function adjustment_return_menambah_stok(): void
    {
        $this->actingAs($this->user);

        $stokAwal = $this->inventory->quantity;

        $this->inventory->addStock(
            quantity: 2,
            type: InventoryAdjustment::TYPE_RETURN,
            reason: 'Retur dari pelanggan',
            userId: $this->user->id
        );

        $this->inventory->refresh();

        $this->assertEquals($stokAwal + 2, $this->inventory->quantity);
    }
}
