<?php

namespace Tests\Unit;

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test untuk Model Inventory
 *
 * Test ini memastikan model Inventory bekerja dengan benar
 * dengan arsitektur baru menggunakan StockMovement
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $category;
    protected Product $product;
    protected Inventory $inventory;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->inventoryService = new InventoryService();
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
        $this->inventory = Inventory::create([
            'product_id' => $this->product->id,
            'barcode' => $this->product->barcode,
            'quantity' => 100,
        ]);
    }

    /** @test */
    public function inventory_memiliki_relasi_ke_produk()
    {
        $this->assertInstanceOf(Product::class, $this->inventory->product);
        $this->assertEquals($this->product->id, $this->inventory->product->id);
    }

    /** @test */
    public function produk_memiliki_relasi_ke_inventory()
    {
        $this->assertInstanceOf(Inventory::class, $this->product->inventory);
        $this->assertEquals($this->inventory->id, $this->product->inventory->id);
    }

    /** @test */
    public function dapat_mengambil_atau_membuat_inventory_untuk_produk()
    {
        // Create a new product without inventory
        $newProduct = Product::create([
            'barcode' => 'NEW001',
            'title' => 'New Product',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 7500,
            'stock' => 0,
        ]);

        $this->assertNull($newProduct->inventory);

        // Get or create should create new inventory
        $inventory = Inventory::getOrCreateForProduct($newProduct);

        $this->assertInstanceOf(Inventory::class, $inventory);
        $this->assertEquals($newProduct->id, $inventory->product_id);
        $this->assertEquals($newProduct->barcode, $inventory->barcode);
    }

    /** @test */
    public function get_or_create_mengembalikan_inventory_yang_ada()
    {
        // Should return existing inventory, not create new
        $inventory = Inventory::getOrCreateForProduct($this->product);

        $this->assertEquals($this->inventory->id, $inventory->id);
    }

    /** @test */
    public function inventory_memiliki_relasi_ke_stock_movements()
    {
        // Create some stock movements
        StockMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'quantity_before' => 100,
            'quantity_after' => 150,
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -10,
            'quantity_before' => 150,
            'quantity_after' => 140,
        ]);

        $this->inventory->refresh();

        $this->assertCount(2, $this->inventory->stockMovements);
    }

    /** @test */
    public function dapat_menghitung_saldo_stok_dari_movements()
    {
        // Create stock movements
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -30,
            'quantity_before' => 100,
            'quantity_after' => 70,
        ]);

        $stockBalance = StockMovement::getCurrentStock($this->product->id);

        $this->assertEquals(70, $stockBalance);
    }

    /** @test */
    public function dapat_sinkronisasi_quantity_dari_movements()
    {
        // Set initial inventory quantity to different value
        $this->inventory->quantity = 50;
        $this->inventory->save();

        // Create stock movements with different total
        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -25,
            'quantity_before' => 100,
            'quantity_after' => 75,
        ]);

        // Sync from movements
        $this->inventory->syncQuantityFromMovements();

        $this->assertEquals(75, $this->inventory->quantity);
    }

    /** @test */
    public function inventory_memiliki_relasi_ke_adjustments()
    {
        $this->actingAs($this->user);

        // Create adjustments using the service
        $this->inventoryService->createAdjustment(
            $this->product,
            10,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Test add',
            null,
            $this->user->id
        );

        $this->inventoryService->createAdjustment(
            $this->product,
            5,
            InventoryAdjustment::TYPE_DAMAGE,
            'Test damage',
            null,
            $this->user->id
        );

        $this->inventory->refresh();

        $this->assertCount(2, $this->inventory->adjustments);
    }

    /** @test */
    public function adjustment_tipe_yang_valid()
    {
        $validTypes = InventoryAdjustment::getTypes();

        $this->assertContains(InventoryAdjustment::TYPE_ADJUSTMENT_IN, $validTypes);
        $this->assertContains(InventoryAdjustment::TYPE_ADJUSTMENT_OUT, $validTypes);
        $this->assertContains(InventoryAdjustment::TYPE_RETURN, $validTypes);
        $this->assertContains(InventoryAdjustment::TYPE_DAMAGE, $validTypes);
        $this->assertContains(InventoryAdjustment::TYPE_CORRECTION, $validTypes);
    }

    /** @test */
    public function adjustment_incoming_types_benar()
    {
        $incomingTypes = InventoryAdjustment::getIncomingTypes();

        $this->assertContains(InventoryAdjustment::TYPE_ADJUSTMENT_IN, $incomingTypes);
        $this->assertContains(InventoryAdjustment::TYPE_RETURN, $incomingTypes);
        $this->assertNotContains(InventoryAdjustment::TYPE_ADJUSTMENT_OUT, $incomingTypes);
        $this->assertNotContains(InventoryAdjustment::TYPE_DAMAGE, $incomingTypes);
    }

    /** @test */
    public function adjustment_outgoing_types_benar()
    {
        $outgoingTypes = InventoryAdjustment::getOutgoingTypes();

        $this->assertContains(InventoryAdjustment::TYPE_ADJUSTMENT_OUT, $outgoingTypes);
        $this->assertContains(InventoryAdjustment::TYPE_DAMAGE, $outgoingTypes);
        $this->assertNotContains(InventoryAdjustment::TYPE_ADJUSTMENT_IN, $outgoingTypes);
        $this->assertNotContains(InventoryAdjustment::TYPE_RETURN, $outgoingTypes);
    }

    /** @test */
    public function adjustment_type_label_bekerja()
    {
        $adjustment = new InventoryAdjustment([
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN,
        ]);
        $this->assertEquals('Adjustment Masuk', $adjustment->type_label);

        $adjustment->type = InventoryAdjustment::TYPE_DAMAGE;
        $this->assertEquals('Barang Rusak', $adjustment->type_label);

        $adjustment->type = InventoryAdjustment::TYPE_RETURN;
        $this->assertEquals('Return Barang', $adjustment->type_label);
    }

    /** @test */
    public function adjustment_is_incoming_dan_is_outgoing_bekerja()
    {
        $incomingAdjustment = new InventoryAdjustment([
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN,
        ]);

        $outgoingAdjustment = new InventoryAdjustment([
            'type' => InventoryAdjustment::TYPE_DAMAGE,
        ]);

        $this->assertTrue($incomingAdjustment->isIncoming());
        $this->assertFalse($incomingAdjustment->isOutgoing());

        $this->assertFalse($outgoingAdjustment->isIncoming());
        $this->assertTrue($outgoingAdjustment->isOutgoing());
    }

    /** @test */
    public function dapat_filter_adjustments_by_product()
    {
        $this->actingAs($this->user);

        // Create another product with inventory
        $product2 = Product::create([
            'barcode' => 'PROD002',
            'title' => 'Product 2',
            'description' => 'Description',
            'category_id' => $this->category->id,
            'buy_price' => 5000,
            'sell_price' => 7500,
            'stock' => 50,
        ]);

        Inventory::create([
            'product_id' => $product2->id,
            'barcode' => $product2->barcode,
            'quantity' => 50,
        ]);

        // Create adjustments for both products
        $this->inventoryService->createAdjustment(
            $this->product,
            10,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Add to product 1'
        );

        $this->inventoryService->createAdjustment(
            $product2,
            20,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Add to product 2'
        );

        $product1Adjustments = InventoryAdjustment::forProduct($this->product->id)->count();
        $product2Adjustments = InventoryAdjustment::forProduct($product2->id)->count();

        $this->assertEquals(1, $product1Adjustments);
        $this->assertEquals(1, $product2Adjustments);
    }

    /** @test */
    public function adjustment_memiliki_relasi_ke_user()
    {
        $this->actingAs($this->user);

        $result = $this->inventoryService->createAdjustment(
            $this->product,
            15,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'Test user tracking',
            null,
            $this->user->id
        );

        $this->assertEquals($this->user->id, $result['adjustment']->user_id);
        $this->assertInstanceOf(User::class, $result['adjustment']->user);
    }

    /** @test */
    public function adjustment_dengan_journal_hanya_menampilkan_yang_punya_jurnal()
    {
        $this->actingAs($this->user);

        // Create adjustment with journal number
        $this->inventoryService->createAdjustment(
            $this->product,
            10,
            InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'With journal'
        );

        // Create adjustment without journal (manually)
        InventoryAdjustment::create([
            'product_id' => $this->product->id,
            'user_id' => $this->user->id,
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'quantity_change' => 5,
            'reason' => 'Without journal',
            'journal_number' => null,
        ]);

        $withJournal = InventoryAdjustment::withJournal()->count();
        $total = InventoryAdjustment::count();

        $this->assertEquals(1, $withJournal);
        $this->assertEquals(2, $total);
    }
}
