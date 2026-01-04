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
 * Unit Test untuk InventoryService
 *
 * Test ini memastikan service inventory berjalan dengan benar
 * menggunakan arsitektur baru dengan StockMovement sebagai ledger utama
 */
class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected User $user;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();
        $this->user = User::factory()->create();
        $this->category = Category::create([
            'name' => 'Test Category',
            'description' => 'Test description',
        ]);
        $this->product = Product::create([
            'barcode' => 'TEST001',
            'title' => 'Test Product',
            'description' => 'Test product description',
            'category_id' => $this->category->id,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 100,
        ]);
    }

    /** @test */
    public function dapat_proses_pembelian_dan_update_inventory()
    {
        $this->actingAs($this->user);

        // Create inventory
        Inventory::create([
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 100,
        ]);

        // Create initial stock movement (baseline stok)
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 10000,
            'total_price' => 1000000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Create purchase
        $purchase = Purchase::create([
            'supplier_name' => 'Test Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 50,
            'purchase_price' => 10000,
            'total_price' => 500000,
        ]);

        $purchase->load('items.product');

        // Process purchase
        $this->inventoryService->processPurchase($purchase);

        // Verify inventory updated
        $inventory = Inventory::where('product_id', $this->product->id)->first();
        $this->assertEquals(150, $inventory->quantity);

        // Verify stock movement created (bukan InventoryAdjustment)
        $movement = StockMovement::where('reference_type', 'purchase')
            ->where('reference_id', $purchase->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(StockMovement::TYPE_PURCHASE, $movement->movement_type);
        $this->assertEquals(50, $movement->quantity);
    }

    /** @test */
    public function dapat_membatalkan_pembelian_dan_update_inventory()
    {
        $this->actingAs($this->user);

        // Create inventory with stock
        $inventory = Inventory::create([
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 100,
        ]);

        // Create initial stock movement (baseline)
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 10000,
            'total_price' => 1000000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Create purchase that we'll process first then reverse
        $purchase = Purchase::create([
            'supplier_name' => 'Test Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 50,
            'purchase_price' => 10000,
            'total_price' => 500000,
        ]);

        $purchase->load('items.product');

        // Process purchase first (100 + 50 = 150)
        $this->inventoryService->processPurchase($purchase);
        $inventory->refresh();
        $this->assertEquals(150, $inventory->quantity);

        // Reverse purchase (150 - 50 = 100)
        $this->inventoryService->reversePurchase($purchase);

        // Verify inventory updated (reduced back to original)
        $inventory->refresh();
        $this->assertEquals(100, $inventory->quantity);
    }

    /** @test */
    public function dapat_membuat_adjustment_penambahan_stok()
    {
        $this->actingAs($this->user);

        // Create inventory
        Inventory::create([
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 100,
        ]);

        // Create initial stock movement (baseline)
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 10000,
            'total_price' => 1000000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Use createAdjustment
        $result = $this->inventoryService->createAdjustment(
            $this->product,
            25,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Manual stock in',
            null,
            $this->user->id
        );

        // Verify inventory updated
        $inventory = Inventory::where('product_id', $this->product->id)->first();
        $this->assertEquals(125, $inventory->quantity);

        // Verify adjustment created with journal number
        $this->assertNotNull($result['adjustment']);
        $this->assertEquals(InventoryAdjustment::TYPE_ADJUSTMENT_IN, $result['adjustment']->type);
        $this->assertEquals(25, $result['adjustment']->quantity_change);
        $this->assertNotNull($result['adjustment']->journal_number);
    }

    /** @test */
    public function dapat_membuat_adjustment_pengurangan_stok()
    {
        $this->actingAs($this->user);

        // Create inventory
        Inventory::create([
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 100,
        ]);

        // Create initial stock movement (baseline)
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 10000,
            'total_price' => 1000000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        $result = $this->inventoryService->createAdjustment(
            $this->product,
            30,
            InventoryAdjustment::TYPE_DAMAGE,
            'Damaged goods',
            null,
            $this->user->id
        );

        // Verify inventory updated
        $inventory = Inventory::where('product_id', $this->product->id)->first();
        $this->assertEquals(70, $inventory->quantity);

        // Verify adjustment
        $this->assertEquals(InventoryAdjustment::TYPE_DAMAGE, $result['adjustment']->type);
        $this->assertEquals(-30, $result['adjustment']->quantity_change);
    }

    /** @test */
    public function dapat_koreksi_stok()
    {
        $this->actingAs($this->user);

        // Create inventory
        Inventory::create([
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 100,
        ]);

        // Create a stock movement so getCurrentStock works
        StockMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        $result = $this->inventoryService->stockCorrection(
            $this->product,
            85,
            'Stock opname correction',
            $this->user->id
        );

        // Verify inventory set to exact value
        $inventory = Inventory::where('product_id', $this->product->id)->first();
        $this->assertEquals(85, $inventory->quantity);

        // Verify adjustment and movement created
        $this->assertNotNull($result['adjustment']);
        $this->assertNotNull($result['movement']);
    }

    /** @test */
    public function dapat_mengambil_riwayat_stok()
    {
        $this->actingAs($this->user);

        // Create inventory
        Inventory::create([
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 100,
        ]);

        // Create some stock movements
        StockMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 10,
            'quantity_before' => 100,
            'quantity_after' => 110,
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 20,
            'quantity_before' => 110,
            'quantity_after' => 130,
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -5,
            'quantity_before' => 130,
            'quantity_after' => 125,
        ]);

        $history = $this->inventoryService->getStockHistory($this->product);

        $this->assertEquals(3, $history->count());
    }

    /** @test */
    public function dapat_mengambil_ringkasan_inventory()
    {
        // Create some products with stock movements for buy price calculation
        $product2 = Product::create([
            'barcode' => 'PROD002',
            'title' => 'Product 2',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 7500,
            'stock' => 50,
        ]);

        $product3 = Product::create([
            'barcode' => 'PROD003',
            'title' => 'Product 3',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'buy_price' => 8000,
            'sell_price' => 12000,
            'stock' => 5, // Low stock
        ]);

        $product4 = Product::create([
            'barcode' => 'PROD004',
            'title' => 'Product 4',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'buy_price' => 3000,
            'sell_price' => 5000,
            'stock' => 0, // Out of stock
        ]);

        // Create stock movements to establish buy prices
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 10000,
            'total_price' => 1000000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        StockMovement::create([
            'product_id' => $product2->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 5000,
            'total_price' => 250000,
            'quantity_before' => 0,
            'quantity_after' => 50,
        ]);

        $summary = $this->inventoryService->getInventorySummary();

        $this->assertArrayHasKey('total_products', $summary);
        $this->assertArrayHasKey('total_stock_value', $summary);
        $this->assertArrayHasKey('total_sell_value', $summary);
        $this->assertArrayHasKey('low_stock_count', $summary);
        $this->assertArrayHasKey('out_of_stock_count', $summary);

        $this->assertEquals(4, $summary['total_products']);
        $this->assertGreaterThan(0, $summary['total_stock_value']);
        $this->assertEquals(1, $summary['low_stock_count']);
        $this->assertEquals(1, $summary['out_of_stock_count']);
    }

    /** @test */
    public function dapat_sinkronisasi_inventory_dengan_produk()
    {
        // Create products without inventory
        Product::create([
            'barcode' => 'SYNC001',
            'title' => 'Sync Product 1',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 7500,
            'stock' => 30,
        ]);

        Product::create([
            'barcode' => 'SYNC002',
            'title' => 'Sync Product 2',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'buy_price' => 3000,
            'sell_price' => 5000,
            'stock' => 20,
        ]);

        // Ensure no inventory exists yet
        $this->assertEquals(0, Inventory::count());

        // Sync inventory
        $synced = $this->inventoryService->syncInventoryWithProducts();

        // All products should now have inventory
        $this->assertEquals(3, Inventory::count()); // 1 from setup + 2 new
        $this->assertTrue($synced > 0);
    }

    /** @test */
    public function membuat_inventory_jika_belum_ada_saat_proses_pembelian()
    {
        $this->actingAs($this->user);

        // Ensure no inventory exists
        $this->assertNull(Inventory::where('product_id', $this->product->id)->first());

        // Create purchase
        $purchase = Purchase::create([
            'supplier_name' => 'Test Supplier',
            'purchase_date' => now(),
            'status' => 'received',
        ]);


        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 50,
            'purchase_price' => 10000,
            'total_price' => 500000,
        ]);

        $purchase->load('items.product');

        // Process purchase should create inventory
        $this->inventoryService->processPurchase($purchase);

        // Verify inventory created
        $inventory = Inventory::where('product_id', $this->product->id)->firstOrFail();
        // Stok berdasarkan StockMovement (0 + 50 = 50)
        $this->assertEquals(50, $inventory->quantity);
    }

    /** @test */
    public function validasi_stok_gagal_jika_tidak_cukup()
    {
        // Create stock movement for initial stock
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
        ]);

        // Request more than available
        $items = [
            ['product_id' => $this->product->id, 'quantity' => 20],
        ];

        $result = $this->inventoryService->validateStockForTransaction($items);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function validasi_stok_berhasil_jika_cukup()
    {
        // Create stock movement for initial stock
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Request less than available
        $items = [
            ['product_id' => $this->product->id, 'quantity' => 50],
        ];

        $result = $this->inventoryService->validateStockForTransaction($items);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
}
