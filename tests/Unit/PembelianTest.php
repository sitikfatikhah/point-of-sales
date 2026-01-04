<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Proses Pembelian
 *
 * Test ini memastikan proses pembelian/purchase bekerja dengan benar
 * dan terintegrasi dengan StockMovement sebagai ledger utama
 */
class PembelianTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected User $user;
    protected Category $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();

        $this->user = User::factory()->create([
            'name' => 'Admin Pembelian',
        ]);

        $this->kategori = Category::create([
            'name' => 'ATK',
            'description' => 'Alat Tulis Kantor',
        ]);
    }

    // ==========================================
    // TEST PEMBUATAN PURCHASE
    // ==========================================

    /** @test */
    public function dapat_membuat_purchase_order(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'PT Supplier ATK',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_name' => 'PT Supplier ATK',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function dapat_menambah_item_ke_purchase(): void
    {
        $produk = $this->buatProduk('Pensil 2B', 2000, 3000, 0);

        $purchase = Purchase::create([
            'supplier_name' => 'Supplier Pensil',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $produk->id,
            'barcode' => $produk->barcode,
            'quantity' => 100,
            'purchase_price' => 2000,
            'total_price' => 200000,
        ]);

        $this->assertDatabaseHas('purchase_items', [
            'id' => $item->id,
            'purchase_id' => $purchase->id,
            'quantity' => 100,
        ]);

        $purchase->refresh();
        $this->assertCount(1, $purchase->items);
    }

    /** @test */
    public function total_purchase_dihitung_dengan_benar(): void
    {
        $produk1 = $this->buatProduk('Buku Tulis', 5000, 8000, 0);
        $produk2 = $this->buatProduk('Pulpen', 3000, 5000, 0);

        $purchase = Purchase::create([
            'supplier_name' => 'Supplier ATK',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $produk1->id,
            'barcode' => $produk1->barcode,
            'quantity' => 50,
            'purchase_price' => 5000,
            'total_price' => 250000, // 50 x 5000
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $produk2->id,
            'barcode' => $produk2->barcode,
            'quantity' => 100,
            'purchase_price' => 3000,
            'total_price' => 300000, // 100 x 3000
        ]);

        $purchase->refresh();

        $total = $purchase->items->sum('total_price');
        $this->assertEquals(550000, $total);
    }

    // ==========================================
    // TEST PROSES PEMBELIAN KE STOK
    // ==========================================

    /** @test */
    public function proses_pembelian_menambah_stok_produk(): void
    {
        $produk = $this->buatProduk('Penghapus', 1000, 2000, 50);
        $this->buatInventory($produk);
        $this->buatInitialMovement($produk, 50, 1000);

        $stokAwal = StockMovement::getCurrentStock($produk->id);
        $this->assertEquals(50, $stokAwal);

        $purchase = $this->buatPurchaseDenganItem($produk, 100, 1000);

        $this->inventoryService->processPurchase($purchase);

        $stokAkhir = StockMovement::getCurrentStock($produk->id);
        $this->assertEquals(150, $stokAkhir); // 50 + 100
    }

    /** @test */
    public function proses_pembelian_membuat_stock_movement(): void
    {
        $produk = $this->buatProduk('Rautan', 1500, 2500, 0);

        $purchase = $this->buatPurchaseDenganItem($produk, 50, 1500);

        $this->inventoryService->processPurchase($purchase);

        $movement = StockMovement::where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->where('product_id', $produk->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(StockMovement::TYPE_PURCHASE, $movement->movement_type);
        $this->assertEquals(50, $movement->quantity);
        $this->assertEquals(1500, $movement->unit_price);
        $this->assertEquals(75000, $movement->total_price);
    }

    /** @test */
    public function proses_pembelian_dengan_multiple_item(): void
    {
        $produk1 = $this->buatProduk('Spidol Hitam', 4000, 6000, 20);
        $produk2 = $this->buatProduk('Spidol Merah', 4000, 6000, 15);

        $this->buatInventory($produk1);
        $this->buatInventory($produk2);
        $this->buatInitialMovement($produk1, 20, 4000);
        $this->buatInitialMovement($produk2, 15, 4000);

        $purchase = Purchase::create([
            'supplier_name' => 'Supplier Spidol',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $produk1->id,
            'barcode' => $produk1->barcode,
            'quantity' => 30,
            'purchase_price' => 4000,
            'total_price' => 120000,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $produk2->id,
            'barcode' => $produk2->barcode,
            'quantity' => 25,
            'purchase_price' => 4000,
            'total_price' => 100000,
        ]);

        $purchase->load('items.product');

        $this->inventoryService->processPurchase($purchase);

        // Verifikasi kedua produk stoknya bertambah
        $this->assertEquals(50, StockMovement::getCurrentStock($produk1->id)); // 20 + 30
        $this->assertEquals(40, StockMovement::getCurrentStock($produk2->id)); // 15 + 25
    }

    // ==========================================
    // TEST PEMBATALAN PEMBELIAN
    // ==========================================

    /** @test */
    public function dapat_membatalkan_pembelian(): void
    {
        $produk = $this->buatProduk('Tip-Ex', 5000, 8000, 30);
        $this->buatInventory($produk);
        $this->buatInitialMovement($produk, 30, 5000);

        $purchase = $this->buatPurchaseDenganItem($produk, 20, 5000);

        // Proses pembelian
        $this->inventoryService->processPurchase($purchase);
        $this->assertEquals(50, StockMovement::getCurrentStock($produk->id)); // 30 + 20

        // Batalkan pembelian
        $this->inventoryService->reversePurchase($purchase);

        // Stok kembali ke semula
        $this->assertEquals(30, StockMovement::getCurrentStock($produk->id));
    }

    /** @test */
    public function pembatalan_pembelian_membuat_stock_movement_negatif(): void
    {
        $produk = $this->buatProduk('Lem Kertas', 3000, 5000, 0);

        $purchase = $this->buatPurchaseDenganItem($produk, 40, 3000);

        $this->inventoryService->processPurchase($purchase);
        $this->inventoryService->reversePurchase($purchase);

        // Ada 2 movement: purchase (+40) dan reverse (-40)
        $movements = StockMovement::where('product_id', $produk->id)->get();
        $this->assertCount(2, $movements);

        // Total stok = 0
        $this->assertEquals(0, StockMovement::getCurrentStock($produk->id));
    }

    // ==========================================
    // TEST HARGA BELI RATA-RATA
    // ==========================================

    /** @test */
    public function average_buy_price_dihitung_dari_semua_pembelian(): void
    {
        $produk = $this->buatProduk('Kertas A4', 35000, 45000, 0);

        // Pembelian pertama: 10 rim @ 35000
        $purchase1 = $this->buatPurchaseDenganItem($produk, 10, 35000);
        $this->inventoryService->processPurchase($purchase1);

        // Pembelian kedua: 10 rim @ 40000 (harga naik)
        $purchase2 = $this->buatPurchaseDenganItem($produk, 10, 40000);
        $this->inventoryService->processPurchase($purchase2);

        // Average = (350000 + 400000) / 20 = 37500
        $avgPrice = StockMovement::getAverageBuyPrice($produk->id);

        $this->assertEquals(37500, $avgPrice);
    }

    /** @test */
    public function average_buy_price_hanya_dari_pembelian_dan_adjustment_in(): void
    {
        $produk = $this->buatProduk('Amplop', 500, 1000, 0);

        // Pembelian: 100 @ 500
        $purchase = $this->buatPurchaseDenganItem($produk, 100, 500);
        $this->inventoryService->processPurchase($purchase);

        // Simulasi penjualan (tidak mempengaruhi average buy price)
        StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -20,
            'unit_price' => 1000, // Ini harga jual, bukan beli
            'total_price' => 20000,
            'quantity_before' => 100,
            'quantity_after' => 80,
        ]);

        // Average masih berdasarkan harga beli (500)
        $avgPrice = StockMovement::getAverageBuyPrice($produk->id);

        $this->assertEquals(500, $avgPrice);
    }

    // ==========================================
    // TEST STATUS PURCHASE
    // ==========================================

    /** @test */
    public function purchase_memiliki_berbagai_status(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier Test',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $purchase->status);

        $purchase->update(['status' => 'received']);
        $this->assertEquals('received', $purchase->fresh()->status);

        $purchase->update(['status' => 'cancelled']);
        $this->assertEquals('cancelled', $purchase->fresh()->status);
    }

    // ==========================================
    // TEST SUPPLIER
    // ==========================================

    /** @test */
    public function purchase_menyimpan_informasi_supplier(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'PT Pena Jaya',
            'notes' => 'Catatan pembelian',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $this->assertEquals('PT Pena Jaya', $purchase->supplier_name);
        $this->assertEquals('Catatan pembelian', $purchase->notes);
    }

    // ==========================================
    // TEST PRODUK BARU DARI PEMBELIAN
    // ==========================================

    /** @test */
    public function pembelian_produk_baru_membuat_inventory(): void
    {
        // Produk baru tanpa inventory
        $produk = Product::create([
            'barcode' => 'NEW001',
            'title' => 'Produk Baru',
            'description' => 'Produk baru deskripsi',
            'category_id' => $this->kategori->id,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 0,
        ]);

        $this->assertNull($produk->inventory);

        // Beli produk baru
        $purchase = $this->buatPurchaseDenganItem($produk, 50, 10000);
        $this->inventoryService->processPurchase($purchase);

        // Stok bertambah via stock movement
        $this->assertEquals(50, StockMovement::getCurrentStock($produk->id));
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    private function buatProduk(string $title, int $buyPrice, int $sellPrice, int $stock): Product
    {
        static $counter = 0;
        $counter++;

        return Product::create([
            'barcode' => 'ATK' . str_pad($counter, 3, '0', STR_PAD_LEFT),
            'title' => $title,
            'description' => $title . ' deskripsi',
            'category_id' => $this->kategori->id,
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'stock' => $stock,
        ]);
    }

    private function buatInventory(Product $produk): Inventory
    {
        return Inventory::create([
            'product_id' => $produk->id,
            'barcode' => $produk->barcode,
            'quantity' => $produk->stock,
        ]);
    }

    private function buatInitialMovement(Product $produk, int $quantity, int $unitPrice): StockMovement
    {
        return StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'quantity_before' => 0,
            'quantity_after' => $quantity,
        ]);
    }

    private function buatPurchaseDenganItem(Product $produk, int $quantity, int $price): Purchase
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier Test',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $produk->id,
            'barcode' => $produk->barcode,
            'quantity' => $quantity,
            'purchase_price' => $price,
            'total_price' => $quantity * $price,
        ]);

        $purchase->load('items.product');

        return $purchase;
    }
}
