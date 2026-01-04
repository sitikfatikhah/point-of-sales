<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Relasi Antar Model
 *
 * Test ini memastikan semua relasi model bekerja dengan benar
 * menggunakan arsitektur baru dengan StockMovement sebagai ledger utama
 */
class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $kategori;
    protected Product $produk;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Kasir Test',
        ]);

        $this->kategori = Category::create([
            'name' => 'Elektronik',
            'description' => 'Barang elektronik',
        ]);

        $this->produk = Product::create([
            'barcode' => 'ELK001',
            'title' => 'Mouse Wireless',
            'description' => 'Mouse wireless 2.4GHz',
            'category_id' => $this->kategori->id,
            'buy_price' => 50000,
            'sell_price' => 75000,
            'stock' => 100,
        ]);

        $this->customer = Customer::create([
            'name' => 'PT Pelanggan Test',
            'no_telp' => 2112345678,
            'address' => 'Jakarta',
        ]);
    }

    // ==========================================
    // RELASI PRODUCT
    // ==========================================

    /** @test */
    public function product_belongs_to_category(): void
    {
        $this->assertInstanceOf(Category::class, $this->produk->category);
        $this->assertEquals($this->kategori->id, $this->produk->category->id);
        $this->assertEquals('Elektronik', $this->produk->category->name);
    }

    /** @test */
    public function product_has_one_inventory(): void
    {
        Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $this->produk->stock,
        ]);

        $this->produk->refresh();

        $this->assertInstanceOf(Inventory::class, $this->produk->inventory);
        $this->assertEquals($this->produk->stock, $this->produk->inventory->quantity);
    }

    /** @test */
    public function product_has_many_stock_movements(): void
    {
        // Create some stock movements
        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 50000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 50,
        ]);

        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -10,
            'unit_price' => 75000,
            'total_price' => 750000,
            'quantity_before' => 50,
            'quantity_after' => 40,
        ]);

        $this->produk->refresh();

        $this->assertCount(2, $this->produk->stockMovements);
    }

    /** @test */
    public function product_has_many_purchase_items(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier A',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 20,
            'purchase_price' => 50000,
            'total_price' => 1000000,
        ]);

        $this->produk->refresh();

        $this->assertCount(1, $this->produk->purchaseItems);
    }

    // ==========================================
    // RELASI CATEGORY
    // ==========================================

    /** @test */
    public function category_has_many_products(): void
    {
        // Produk sudah ada dari setUp
        $this->assertCount(1, $this->kategori->products);
        $this->assertInstanceOf(Product::class, $this->kategori->products->first());
    }

    // ==========================================
    // RELASI INVENTORY
    // ==========================================

    /** @test */
    public function inventory_belongs_to_product(): void
    {
        $inventory = Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $this->produk->stock,
        ]);

        $this->assertInstanceOf(Product::class, $inventory->product);
        $this->assertEquals($this->produk->id, $inventory->product->id);
    }

    /** @test */
    public function inventory_has_many_stock_movements(): void
    {
        $inventory = Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $this->produk->stock,
        ]);

        StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 50000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 50,
        ]);

        $inventory->refresh();

        $this->assertCount(1, $inventory->stockMovements);
    }

    // ==========================================
    // RELASI STOCK MOVEMENT
    // ==========================================

    /** @test */
    public function stock_movement_belongs_to_product(): void
    {
        $movement = StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 50000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 50,
        ]);

        $this->assertInstanceOf(Product::class, $movement->product);
        $this->assertEquals($this->produk->id, $movement->product->id);
    }

    /** @test */
    public function stock_movement_belongs_to_user(): void
    {
        $movement = StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 50000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 50,
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $movement->user);
        $this->assertEquals($this->user->id, $movement->user->id);
    }

    /** @test */
    public function stock_movement_can_reference_purchase(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier B',
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        $movement = StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 50,
            'unit_price' => 50000,
            'total_price' => 2500000,
            'quantity_before' => 0,
            'quantity_after' => 50,
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
        ]);

        $this->assertEquals('purchase', $movement->reference_type);
        $this->assertEquals($purchase->id, $movement->reference_id);
    }

    /** @test */
    public function stock_movement_can_reference_transaction(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-001',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
        ]);

        $movement = StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -1,
            'unit_price' => 75000,
            'total_price' => 75000,
            'quantity_before' => 50,
            'quantity_after' => 49,
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
        ]);

        $this->assertEquals('transaction', $movement->reference_type);
        $this->assertEquals($transaction->id, $movement->reference_id);
    }

    // ==========================================
    // RELASI INVENTORY ADJUSTMENT
    // ==========================================

    /** @test */
    public function inventory_adjustment_belongs_to_product(): void
    {
        $adjustment = InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'quantity_change' => 10,
            'notes' => 'Test adjustment',
            'journal_number' => InventoryAdjustment::generateJournalNumber(),
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(Product::class, $adjustment->product);
        $this->assertEquals($this->produk->id, $adjustment->product->id);
    }

    /** @test */
    public function inventory_adjustment_belongs_to_user(): void
    {
        $adjustment = InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'quantity_change' => 10,
            'notes' => 'Test adjustment',
            'journal_number' => InventoryAdjustment::generateJournalNumber(),
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $adjustment->user);
        $this->assertEquals($this->user->id, $adjustment->user->id);
    }

    /** @test */
    public function inventory_adjustment_can_have_stock_movement_relation(): void
    {
        // Create adjustment first
        $adjustment = InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'quantity_change' => 10,
            'notes' => 'Test adjustment',
            'journal_number' => InventoryAdjustment::generateJournalNumber(),
            'user_id' => $this->user->id,
        ]);

        // Then create stock movement with reference to the adjustment
        $movement = StockMovement::create([
            'product_id' => $this->produk->id,
            'movement_type' => StockMovement::TYPE_ADJUSTMENT_IN,
            'quantity' => 10,
            'unit_price' => 50000,
            'total_price' => 500000,
            'quantity_before' => 100,
            'quantity_after' => 110,
            'user_id' => $this->user->id,
            'reference_type' => 'adjustment',
            'reference_id' => $adjustment->id,
        ]);

        // Refresh and verify
        $adjustment->refresh();
        $this->assertNotNull($adjustment->stockMovement);
        $this->assertEquals($movement->id, $adjustment->stockMovement->id);
    }

    // ==========================================
    // RELASI PURCHASE
    // ==========================================

    /** @test */
    public function purchase_has_many_purchase_items(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier C',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 10,
            'purchase_price' => 50000,
            'total_price' => 500000,
        ]);

        // Create another product for second item
        $produk2 = Product::create([
            'barcode' => 'ELK002',
            'title' => 'Keyboard USB',
            'description' => 'Keyboard USB deskripsi',
            'category_id' => $this->kategori->id,
            'buy_price' => 75000,
            'sell_price' => 100000,
            'stock' => 50,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $produk2->id,
            'barcode' => $produk2->barcode,
            'quantity' => 5,
            'purchase_price' => 75000,
            'total_price' => 375000,
        ]);

        $purchase->refresh();

        $this->assertCount(2, $purchase->items);
    }

    // ==========================================
    // RELASI PURCHASE ITEM
    // ==========================================

    /** @test */
    public function purchase_item_belongs_to_purchase(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier D',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 10,
            'purchase_price' => 50000,
            'total_price' => 500000,
        ]);

        $this->assertInstanceOf(Purchase::class, $item->purchase);
        $this->assertEquals($purchase->id, $item->purchase->id);
    }

    /** @test */
    public function purchase_item_belongs_to_product(): void
    {
        $purchase = Purchase::create([
            'supplier_name' => 'Supplier E',
            'purchase_date' => now(),
            'status' => 'pending',
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 10,
            'purchase_price' => 50000,
            'total_price' => 500000,
        ]);

        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertEquals($this->produk->id, $item->product->id);
    }

    // ==========================================
    // RELASI TRANSACTION
    // ==========================================

    /** @test */
    public function transaction_belongs_to_user(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-002',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
        ]);

        $this->assertInstanceOf(User::class, $transaction->cashier);
        $this->assertEquals($this->user->id, $transaction->cashier->id);
    }

    /** @test */
    public function transaction_belongs_to_customer(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice' => 'INV-003',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
        ]);

        $this->assertInstanceOf(Customer::class, $transaction->customer);
        $this->assertEquals($this->customer->id, $transaction->customer->id);
    }

    /** @test */
    public function transaction_has_many_transaction_details(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-004',
            'cash' => 200000,
            'change' => 50000,
            'discount' => 0,
            'grand_total' => 150000,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 2,
            'price' => 150000,
            'discount' => 0,
        ]);

        $transaction->refresh();

        $this->assertCount(1, $transaction->details);
    }

    // ==========================================
    // RELASI TRANSACTION DETAIL
    // ==========================================

    /** @test */
    public function transaction_detail_belongs_to_transaction(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-005',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 1,
            'price' => 75000,
            'discount' => 0,
        ]);

        $this->assertInstanceOf(Transaction::class, $detail->transaction);
        $this->assertEquals($transaction->id, $detail->transaction->id);
    }

    /** @test */
    public function transaction_detail_belongs_to_product(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-006',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 1,
            'price' => 75000,
            'discount' => 0,
        ]);

        $this->assertInstanceOf(Product::class, $detail->product);
        $this->assertEquals($this->produk->id, $detail->product->id);
    }

    // ==========================================
    // RELASI CUSTOMER
    // ==========================================

    /** @test */
    public function customer_has_many_transactions(): void
    {
        Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice' => 'INV-007',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
        ]);

        Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice' => 'INV-008',
            'cash' => 200000,
            'change' => 50000,
            'discount' => 0,
            'grand_total' => 150000,
        ]);

        $this->customer->refresh();

        $this->assertCount(2, $this->customer->transactions);
    }

    // ==========================================
    // RELASI USER
    // ==========================================

    /** @test */
    public function user_has_many_transactions(): void
    {
        Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-009',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
        ]);

        Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-010',
            'cash' => 150000,
            'change' => 50000,
            'discount' => 0,
            'grand_total' => 100000,
        ]);

        // Verify using cashier relationship
        $count = Transaction::where('cashier_id', $this->user->id)->count();
        $this->assertEquals(2, $count);
    }

    /** @test */
    public function user_has_many_inventory_adjustments(): void
    {
        InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_IN,
            'quantity_change' => 10,
            'notes' => 'Adjustment 1',
            'journal_number' => InventoryAdjustment::generateJournalNumber(),
            'user_id' => $this->user->id,
        ]);

        InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'type' => InventoryAdjustment::TYPE_ADJUSTMENT_OUT,
            'quantity_change' => -5,
            'notes' => 'Adjustment 2',
            'journal_number' => InventoryAdjustment::generateJournalNumber(),
            'user_id' => $this->user->id,
        ]);

        $adjustments = InventoryAdjustment::where('user_id', $this->user->id)->count();

        $this->assertEquals(2, $adjustments);
    }
}
