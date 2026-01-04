<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Profit;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Exception;

/**
 * Unit Test Validasi Stok dan Profit
 *
 * Test ini memastikan:
 * - Transaksi diblokir jika stok produk = 0
 * - Transaksi diblokir jika stok tidak mencukupi
 * - Profit dihitung menggunakan average buy price
 * - Profit = (sell_price - average_buy_price) * quantity
 */
class StokValidasiDanProfitTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected User $kasir;
    protected Customer $pelanggan;
    protected Category $kategori;
    protected Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();

        // Setup permission
        Permission::firstOrCreate(['name' => 'transactions-access', 'guard_name' => 'web']);

        $this->kasir = User::factory()->create(['name' => 'Kasir Test']);
        $this->kasir->givePermissionTo('transactions-access');

        $this->pelanggan = Customer::create([
            'name' => 'Pembeli Test',
            'no_telp' => '08123456789',
            'address' => 'Alamat Test',
        ]);

        $this->kategori = Category::create([
            'name' => 'Makanan',
            'description' => 'Kategori makanan',
        ]);

        $this->produk = Product::create([
            'barcode' => 'MKN001',
            'title' => 'Snack Kentang',
            'description' => 'Snack kentang renyah',
            'category_id' => $this->kategori->id,
            'sell_price' => 15000,
            'stock' => 0, // Stok awal 0
        ]);
    }

    // ==========================================
    // TEST VALIDASI STOK
    // ==========================================

    /** @test */
    public function validasi_gagal_jika_stok_nol(): void
    {
        $this->actingAs($this->kasir);

        // Buat cart item dengan produk yang stok = 0
        $cartItem = (object)[
            'product_id' => $this->produk->id,
            'product' => $this->produk,
            'quantity' => 1,
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Stok produk '{$this->produk->title}' habis");

        $this->inventoryService->validateStockForTransaction([$cartItem]);
    }

    /** @test */
    public function validasi_gagal_jika_stok_tidak_mencukupi(): void
    {
        $this->actingAs($this->kasir);

        // Tambah stok 5 unit
        $this->tambahStokViaPurchase(5, 10000);

        // Minta 10 unit (lebih dari stok)
        $cartItem = (object)[
            'product_id' => $this->produk->id,
            'product' => $this->produk,
            'quantity' => 10,
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("tidak mencukupi");

        $this->inventoryService->validateStockForTransaction([$cartItem]);
    }

    /** @test */
    public function validasi_berhasil_jika_stok_mencukupi(): void
    {
        $this->actingAs($this->kasir);

        // Tambah stok 20 unit
        $this->tambahStokViaPurchase(20, 10000);

        // Minta 5 unit (kurang dari stok)
        $cartItem = (object)[
            'product_id' => $this->produk->id,
            'product' => $this->produk,
            'quantity' => 5,
        ];

        // Tidak boleh throw exception
        $this->inventoryService->validateStockForTransaction([$cartItem]);
        $this->assertTrue(true); // Assertion untuk memastikan tidak ada exception
    }

    /** @test */
    public function validasi_berhasil_jika_stok_sama_dengan_permintaan(): void
    {
        $this->actingAs($this->kasir);

        // Tambah stok 10 unit
        $this->tambahStokViaPurchase(10, 10000);

        // Minta 10 unit (sama dengan stok)
        $cartItem = (object)[
            'product_id' => $this->produk->id,
            'product' => $this->produk,
            'quantity' => 10,
        ];

        $this->inventoryService->validateStockForTransaction([$cartItem]);
        $this->assertTrue(true);
    }

    // ==========================================
    // TEST PERHITUNGAN PROFIT
    // ==========================================

    /** @test */
    public function profit_dihitung_dari_average_buy_price(): void
    {
        $this->actingAs($this->kasir);

        // Pembelian: 10 unit @ Rp 10.000 = Rp 100.000
        $this->tambahStokViaPurchase(10, 10000);

        // Average buy price = 10.000
        $averageBuyPrice = StockMovement::getAverageBuyPrice($this->produk->id);
        $this->assertEquals(10000, $averageBuyPrice);

        // Penjualan: 5 unit @ Rp 15.000 = Rp 75.000
        // Profit = (15.000 - 10.000) * 5 = Rp 25.000
        $expectedProfit = ($this->produk->sell_price - $averageBuyPrice) * 5;
        $this->assertEquals(25000, $expectedProfit);
    }

    /** @test */
    public function profit_dihitung_dengan_average_dari_multiple_purchase(): void
    {
        $this->actingAs($this->kasir);

        // Pembelian 1: 10 unit @ Rp 10.000 = Rp 100.000
        $this->tambahStokViaPurchase(10, 10000);

        // Pembelian 2: 10 unit @ Rp 12.000 = Rp 120.000
        $this->tambahStokViaPurchase(10, 12000);

        // Total: 20 unit, Total harga: Rp 220.000
        // Average = 220.000 / 20 = 11.000
        $averageBuyPrice = StockMovement::getAverageBuyPrice($this->produk->id);
        $this->assertEquals(11000, $averageBuyPrice);

        // Jual 5 unit @ Rp 15.000
        // Profit = (15.000 - 11.000) * 5 = Rp 20.000
        $expectedProfit = ($this->produk->sell_price - $averageBuyPrice) * 5;
        $this->assertEquals(20000, $expectedProfit);
    }

    /** @test */
    public function profit_tetap_positif_jika_jual_diatas_average(): void
    {
        $this->actingAs($this->kasir);

        $this->tambahStokViaPurchase(10, 10000);

        $averageBuyPrice = StockMovement::getAverageBuyPrice($this->produk->id);
        $profit = ($this->produk->sell_price - $averageBuyPrice) * 1;

        $this->assertGreaterThan(0, $profit);
    }

    /** @test */
    public function profit_negatif_jika_jual_dibawah_average(): void
    {
        $this->actingAs($this->kasir);

        // Set sell price lebih rendah dari buy price
        $this->produk->sell_price = 8000;
        $this->produk->save();

        $this->tambahStokViaPurchase(10, 10000);

        $averageBuyPrice = StockMovement::getAverageBuyPrice($this->produk->id);
        $profit = ($this->produk->sell_price - $averageBuyPrice) * 1;

        $this->assertLessThan(0, $profit);
        $this->assertEquals(-2000, $profit);
    }

    // ==========================================
    // TEST INTEGRASI TRANSAKSI
    // ==========================================

    /** @test */
    public function transaksi_lengkap_dengan_profit_menggunakan_average_buy_price(): void
    {
        $this->actingAs($this->kasir);

        // Setup: Tambah stok
        $this->tambahStokViaPurchase(20, 10000);
        $this->produk->stock = 20;
        $this->produk->save();

        Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 20,
        ]);

        // Buat transaksi
        $transaction = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-TEST-001',
            'cash' => 50000,
            'change' => 5000,
            'discount' => 0,
            'grand_total' => 45000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Buat detail transaksi: 3 unit
        $detail = TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 3,
            'price' => 45000, // 3 x 15.000
            'discount' => 0,
        ]);

        // Hitung profit menggunakan average buy price
        $averageBuyPrice = StockMovement::getAverageBuyPrice($this->produk->id);
        $totalBuyPrice = $averageBuyPrice * $detail->quantity;
        $totalSellPrice = $this->produk->sell_price * $detail->quantity;
        $profitAmount = $totalSellPrice - $totalBuyPrice;

        // Simpan profit
        $profit = Profit::create([
            'transaction_id' => $transaction->id,
            'total' => $profitAmount,
        ]);

        // Verifikasi
        // Profit = (15.000 - 10.000) * 3 = 15.000
        $this->assertEquals(15000, $profit->total);

        // Process stock movement
        $this->inventoryService->processTransaction($transaction->load('details.product'));

        // Verifikasi stok berkurang
        $currentStock = StockMovement::getCurrentStock($this->produk->id);
        $this->assertEquals(17, $currentStock); // 20 - 3 = 17
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    protected function tambahStokViaPurchase(int $quantity, float $unitPrice): void
    {
        $currentStock = StockMovement::getCurrentStock($this->produk->id);

        StockMovement::create([
            'product_id' => $this->produk->id,
            'user_id' => $this->kasir->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'quantity_before' => $currentStock,
            'quantity_after' => $currentStock + $quantity,
            'notes' => 'Test purchase',
        ]);
    }
}
