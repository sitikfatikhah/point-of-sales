<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Profit;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Relasi Antar Model
 *
 * Test ini memastikan semua relasi database berfungsi dengan benar:
 * - One-to-Many relationships
 * - Many-to-One relationships
 * - Has-One relationships
 * - Through relationships
 */
class RelasiModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $kategori;
    protected Product $produk;
    protected Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Kasir Relasi Test',
        ]);

        $this->kategori = Category::create([
            'name' => 'Elektronik',
            'description' => 'Barang elektronik',
        ]);

        $this->produk = Product::create([
            'barcode' => 'ELK001',
            'title' => 'Charger HP',
            'description' => 'Charger HP fast charging',
            'category_id' => $this->kategori->id,
            'buy_price' => 25000,
            'sell_price' => 40000,
            'stock' => 100,
        ]);

        $this->pelanggan = Customer::create([
            'name' => 'Agus Pelanggan',
            'no_telp' => '08199887766',
            'address' => 'Jl. Relasi No. 1',
        ]);
    }

    // ==========================================
    // TEST RELASI CATEGORY - PRODUCT
    // ==========================================

    /** @test */
    public function kategori_memiliki_banyak_produk(): void
    {
        // Buat produk tambahan dalam kategori yang sama
        Product::create([
            'barcode' => 'ELK002',
            'title' => 'Kabel Data',
            'description' => 'Kabel data type-C',
            'category_id' => $this->kategori->id,
            'buy_price' => 15000,
            'sell_price' => 25000,
            'stock' => 50,
        ]);

        Product::create([
            'barcode' => 'ELK003',
            'title' => 'Earphone',
            'description' => 'Earphone stereo',
            'category_id' => $this->kategori->id,
            'buy_price' => 20000,
            'sell_price' => 35000,
            'stock' => 75,
        ]);

        $this->kategori->refresh();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $this->kategori->products);
        $this->assertCount(3, $this->kategori->products);
    }

    /** @test */
    public function produk_memiliki_satu_kategori(): void
    {
        $this->assertInstanceOf(Category::class, $this->produk->category);
        $this->assertEquals($this->kategori->id, $this->produk->category->id);
        $this->assertEquals('Elektronik', $this->produk->category->name);
    }

    /** @test */
    public function produk_dengan_kategori_berbeda_terpisah(): void
    {
        $kategoriBaru = Category::create([
            'name' => 'Aksesoris',
            'description' => 'Aksesoris HP',
        ]);

        $produkAksesoris = Product::create([
            'barcode' => 'AKS001',
            'title' => 'Case HP',
            'description' => 'Case HP silikon',
            'category_id' => $kategoriBaru->id,
            'buy_price' => 10000,
            'sell_price' => 20000,
            'stock' => 200,
        ]);

        $this->assertEquals($kategoriBaru->id, $produkAksesoris->category->id);
        $this->assertNotEquals($this->kategori->id, $produkAksesoris->category->id);
    }

    // ==========================================
    // TEST RELASI PRODUCT - INVENTORY
    // ==========================================

    /** @test */
    public function produk_memiliki_satu_inventory(): void
    {
        $inventory = Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $this->produk->stock,
        ]);

        $this->produk->refresh();

        $this->assertInstanceOf(Inventory::class, $this->produk->inventory);
        $this->assertEquals($inventory->id, $this->produk->inventory->id);
    }

    /** @test */
    public function inventory_terhubung_ke_satu_produk(): void
    {
        $inventory = Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $this->produk->stock,
        ]);

        $this->assertInstanceOf(Product::class, $inventory->product);
        $this->assertEquals($this->produk->id, $inventory->product->id);
    }

    // ==========================================
    // TEST RELASI PRODUCT - INVENTORY ADJUSTMENT
    // ==========================================

    /** @test */
    public function produk_memiliki_banyak_inventory_adjustment(): void
    {
        // Buat beberapa adjustment
        InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => 'in',
            'quantity_before' => 100,
            'quantity_change' => 50,
            'quantity_after' => 150,
            'reason' => 'Restock',
        ]);

        InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => 'out',
            'quantity_before' => 150,
            'quantity_change' => -10,
            'quantity_after' => 140,
            'reason' => 'Penjualan',
        ]);

        InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => 'damage',
            'quantity_before' => 140,
            'quantity_change' => -5,
            'quantity_after' => 135,
            'reason' => 'Barang rusak',
        ]);

        $this->produk->refresh();

        $this->assertCount(3, $this->produk->inventoryAdjustments);
    }

    /** @test */
    public function inventory_adjustment_terhubung_ke_produk(): void
    {
        $adjustment = InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => 'in',
            'quantity_before' => 100,
            'quantity_change' => 25,
            'quantity_after' => 125,
            'reason' => 'Test relasi',
        ]);

        $this->assertInstanceOf(Product::class, $adjustment->product);
        $this->assertEquals($this->produk->id, $adjustment->product->id);
        $this->assertEquals('Charger HP', $adjustment->product->title);
    }

    /** @test */
    public function inventory_adjustment_terhubung_ke_user(): void
    {
        $adjustment = InventoryAdjustment::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->user->id,
            'type' => 'correction',
            'quantity_before' => 100,
            'quantity_change' => 10,
            'quantity_after' => 110,
            'reason' => 'Test user relasi',
        ]);

        $this->assertInstanceOf(User::class, $adjustment->user);
        $this->assertEquals($this->user->id, $adjustment->user->id);
        $this->assertEquals('Kasir Relasi Test', $adjustment->user->name);
    }

    // ==========================================
    // TEST RELASI TRANSACTION
    // ==========================================

    /** @test */
    public function transaksi_terhubung_ke_kasir(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REL001',
            'cash' => 50000,
            'change' => 10000,
            'discount' => 0,
            'grand_total' => 40000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertInstanceOf(User::class, $transaksi->cashier);
        $this->assertEquals($this->user->id, $transaksi->cashier->id);
    }

    /** @test */
    public function transaksi_terhubung_ke_pelanggan(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REL002',
            'cash' => 50000,
            'change' => 10000,
            'discount' => 0,
            'grand_total' => 40000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertInstanceOf(Customer::class, $transaksi->customer);
        $this->assertEquals($this->pelanggan->id, $transaksi->customer->id);
        $this->assertEquals('Agus Pelanggan', $transaksi->customer->name);
    }

    /** @test */
    public function transaksi_memiliki_banyak_detail(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REL003',
            'cash' => 100000,
            'change' => 20000,
            'discount' => 0,
            'grand_total' => 80000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Buat 2 detail
        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 1,
            'price' => 40000,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 1,
            'price' => 40000,
        ]);

        $transaksi->refresh();

        $this->assertCount(2, $transaksi->details);
    }

    /** @test */
    public function transaksi_memiliki_banyak_profit(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REL004',
            'cash' => 80000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 80000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => 15000,
        ]);

        Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => 15000,
        ]);

        $transaksi->refresh();

        $this->assertCount(2, $transaksi->profits);
        $this->assertEquals(30000, $transaksi->profits->sum('total'));
    }

    // ==========================================
    // TEST RELASI TRANSACTION DETAIL
    // ==========================================

    /** @test */
    public function detail_transaksi_terhubung_ke_transaksi(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REL005',
            'cash' => 50000,
            'change' => 10000,
            'discount' => 0,
            'grand_total' => 40000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 1,
            'price' => 40000,
        ]);

        $this->assertInstanceOf(Transaction::class, $detail->transaction);
        $this->assertEquals($transaksi->id, $detail->transaction->id);
        $this->assertEquals('TRX-REL005', $detail->transaction->invoice);
    }

    /** @test */
    public function detail_transaksi_terhubung_ke_produk(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REL006',
            'cash' => 50000,
            'change' => 10000,
            'discount' => 0,
            'grand_total' => 40000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 1,
            'price' => 40000,
        ]);

        $this->assertInstanceOf(Product::class, $detail->product);
        $this->assertEquals($this->produk->id, $detail->product->id);
    }

    // ==========================================
    // TEST RELASI PROFIT
    // ==========================================

    /** @test */
    public function profit_terhubung_ke_transaksi(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REL007',
            'cash' => 50000,
            'change' => 10000,
            'discount' => 0,
            'grand_total' => 40000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $profit = Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => 15000,
        ]);

        $this->assertInstanceOf(Transaction::class, $profit->transaction);
        $this->assertEquals($transaksi->id, $profit->transaction->id);
    }

    // ==========================================
    // TEST INTEGRITAS REFERENSI
    // ==========================================

    /** @test */
    public function cascade_data_transaksi_lengkap(): void
    {
        // Buat transaksi lengkap dengan semua relasi
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-CASCADE',
            'cash' => 100000,
            'change' => 20000,
            'discount' => 0,
            'grand_total' => 80000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 2,
            'price' => 80000,
        ]);

        $profit = Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => 30000,
        ]);

        // Verifikasi semua relasi dapat diakses dari transaksi
        $transaksi->refresh();
        $transaksi->load(['cashier', 'customer', 'details.product', 'profits']);

        // Kasir
        $this->assertEquals('Kasir Relasi Test', $transaksi->cashier->name);

        // Pelanggan
        $this->assertEquals('Agus Pelanggan', $transaksi->customer->name);

        // Detail -> Produk -> Kategori
        $this->assertEquals('Charger HP', $transaksi->details->first()->product->title);
        $this->assertEquals('Elektronik', $transaksi->details->first()->product->category->name);

        // Profit
        $this->assertEquals(30000, $transaksi->profits->sum('total'));
    }

    /** @test */
    public function eager_loading_berfungsi_dengan_benar(): void
    {
        // Buat beberapa transaksi
        for ($i = 1; $i <= 3; $i++) {
            $trx = Transaction::create([
                'cashier_id' => $this->user->id,
                'customer_id' => $this->pelanggan->id,
                'invoice' => "TRX-EAGER-{$i}",
                'cash' => 50000,
                'change' => 10000,
                'discount' => 0,
                'grand_total' => 40000,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ]);

            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'product_id' => $this->produk->id,
                'barcode' => $this->produk->barcode,
            'discount' => 0,
                'quantity' => 1,
                'price' => 40000,
            ]);

            Profit::create([
                'transaction_id' => $trx->id,
                'total' => 15000,
            ]);
        }

        // Query dengan eager loading
        $transactions = Transaction::with(['cashier', 'customer', 'details.product', 'profits'])
            ->where('invoice', 'like', 'TRX-EAGER-%')
            ->get();

        $this->assertCount(3, $transactions);

        foreach ($transactions as $trx) {
            // Semua relasi harus sudah di-load
            $this->assertTrue($trx->relationLoaded('cashier'));
            $this->assertTrue($trx->relationLoaded('customer'));
            $this->assertTrue($trx->relationLoaded('details'));
            $this->assertTrue($trx->relationLoaded('profits'));

            // Verifikasi data
            $this->assertNotNull($trx->cashier);
            $this->assertNotNull($trx->customer);
            $this->assertCount(1, $trx->details);
            $this->assertCount(1, $trx->profits);
        }
    }

    // ==========================================
    // TEST HASMANYTHROUGH
    // ==========================================

    /** @test */
    public function produk_dapat_mengakses_transaksi_melalui_detail(): void
    {
        // Buat beberapa transaksi untuk produk yang sama
        for ($i = 1; $i <= 3; $i++) {
            $trx = Transaction::create([
                'cashier_id' => $this->user->id,
                'customer_id' => $this->pelanggan->id,
                'invoice' => "TRX-THROUGH-{$i}",
                'cash' => 50000,
                'change' => 10000,
                'discount' => 0,
                'grand_total' => 40000,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ]);

            TransactionDetail::create([
                'transaction_id' => $trx->id,
                'product_id' => $this->produk->id,
                'barcode' => $this->produk->barcode,
            'discount' => 0,
                'quantity' => 1,
                'price' => 40000,
            ]);
        }

        $this->produk->refresh();

        // Produk memiliki relasi ke transaksi melalui transactionDetails
        $this->assertCount(3, $this->produk->transactionDetails);

        // Setiap detail terhubung ke transaksi
        foreach ($this->produk->transactionDetails as $detail) {
            $this->assertNotNull($detail->transaction);
        }
    }
}
