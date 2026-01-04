<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Proses Transaksi Penjualan
 *
 * Test ini memastikan proses transaksi penjualan bekerja dengan benar
 * dan menghitung profit berdasarkan average buy price dari StockMovement
 */
class TransaksiTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;
    protected User $user;
    protected Category $kategori;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();

        $this->user = User::factory()->create([
            'name' => 'Kasir',
        ]);

        $this->kategori = Category::create([
            'name' => 'Makanan',
            'description' => 'Kategori makanan',
        ]);

        $this->customer = Customer::create([
            'name' => 'Pelanggan Umum',
            'no_telp' => 8123456789,
            'address' => 'Jakarta',
        ]);
    }

    // ==========================================
    // TEST PEMBUATAN TRANSAKSI
    // ==========================================

    /** @test */
    public function dapat_membuat_transaksi(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-' . date('Ymd') . '-001',
            'total_amount' => 50000,
            'total_profit' => 15000,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function dapat_membuat_transaksi_dengan_customer(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-' . date('Ymd') . '-002',
            'total_amount' => 100000,
            'total_profit' => 30000,
        ]);

        $this->assertEquals($this->customer->id, $transaction->customer_id);
        $this->assertInstanceOf(Customer::class, $transaction->customer);
    }

    /** @test */
    public function invoice_number_unik(): void
    {
        $invoiceNumber = 'INV-' . date('Ymd') . '-003';

        Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => $invoiceNumber,
            'total_amount' => 50000,
            'total_profit' => 15000,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => $invoiceNumber, // Duplicate
            'total_amount' => 75000,
            'total_profit' => 20000,
        ]);
    }

    // ==========================================
    // TEST DETAIL TRANSAKSI
    // ==========================================

    /** @test */
    public function dapat_menambah_detail_ke_transaksi(): void
    {
        $produk = $this->buatProdukDenganStok('Roti Tawar', 8000, 12000, 100);

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-' . date('Ymd') . '-004',
            'total_amount' => 24000,
            'total_profit' => 8000,
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $produk->id,
            'barcode' => $produk->barcode,
            'quantity' => 2,
            'sell_price' => 12000,
            'total_price' => 24000,
            'buy_price' => 8000,
            'profit' => 8000, // (12000 - 8000) x 2
        ]);

        $transaction->refresh();
        $this->assertCount(1, $transaction->details);
        $this->assertEquals($produk->id, $detail->product_id);
    }

    // ==========================================
    // TEST PERHITUNGAN PROFIT
    // ==========================================

    /** @test */
    public function profit_dihitung_dari_selisih_harga_jual_dan_harga_beli(): void
    {
        $produk = $this->buatProdukDenganStok('Kue Bolu', 15000, 25000, 50);

        // Profit per item = 25000 - 15000 = 10000
        // Quantity = 3
        // Total profit = 30000

        $detail = TransactionDetail::create([
            'transaction_id' => $this->buatTransaksi()->id,
            'product_id' => $produk->id,
            'barcode' => $produk->barcode,
            'quantity' => 3,
            'sell_price' => 25000,
            'total_price' => 75000,
            'buy_price' => 15000,
            'profit' => 30000, // (25000 - 15000) x 3
        ]);

        $this->assertEquals(30000, $detail->profit);
    }

    /** @test */
    public function profit_menggunakan_average_buy_price_dari_stock_movement(): void
    {
        $produk = $this->buatProduk('Mie Instan', 2000, 3500);

        // Pembelian pertama: 100 @ 2000 = 200000
        StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 2000,
            'total_price' => 200000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Pembelian kedua: 100 @ 2500 = 250000
        StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 100,
            'unit_price' => 2500,
            'total_price' => 250000,
            'quantity_before' => 100,
            'quantity_after' => 200,
        ]);

        // Average buy price = (200000 + 250000) / 200 = 2250
        $avgBuyPrice = StockMovement::getAverageBuyPrice($produk->id);
        $this->assertEquals(2250, $avgBuyPrice);

        // Jual 10 unit @ 3500
        // Profit = (3500 - 2250) x 10 = 12500
        $profitPerItem = 3500 - $avgBuyPrice;
        $totalProfit = $profitPerItem * 10;

        $this->assertEquals(12500, $totalProfit);
    }

    /** @test */
    public function total_profit_transaksi_adalah_jumlah_profit_semua_item(): void
    {
        $produk1 = $this->buatProdukDenganStok('Snack A', 5000, 8000, 50);
        $produk2 = $this->buatProdukDenganStok('Snack B', 7000, 10000, 50);

        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-' . date('Ymd') . '-005',
            'total_amount' => 0,
            'total_profit' => 0,
        ]);

        // Detail 1: profit = (8000 - 5000) x 2 = 6000
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $produk1->id,
            'barcode' => $produk1->barcode,
            'quantity' => 2,
            'sell_price' => 8000,
            'total_price' => 16000,
            'buy_price' => 5000,
            'profit' => 6000,
        ]);

        // Detail 2: profit = (10000 - 7000) x 3 = 9000
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $produk2->id,
            'barcode' => $produk2->barcode,
            'quantity' => 3,
            'sell_price' => 10000,
            'total_price' => 30000,
            'buy_price' => 7000,
            'profit' => 9000,
        ]);

        $transaction->refresh();

        $totalAmount = $transaction->details->sum('total_price');
        $totalProfit = $transaction->details->sum('profit');

        $this->assertEquals(46000, $totalAmount);
        $this->assertEquals(15000, $totalProfit);
    }

    // ==========================================
    // TEST PENGURANGAN STOK
    // ==========================================

    /** @test */
    public function transaksi_mengurangi_stok_via_stock_movement(): void
    {
        $produk = $this->buatProdukDenganStok('Minuman Kaleng', 5000, 8000, 100);

        $stokAwal = StockMovement::getCurrentStock($produk->id);
        $this->assertEquals(100, $stokAwal);

        $transaction = $this->buatTransaksi();

        // Simulasi penjualan 5 unit
        StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -5, // Negatif untuk penjualan
            'unit_price' => 8000,
            'total_price' => 40000,
            'quantity_before' => 100,
            'quantity_after' => 95,
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
        ]);

        $stokAkhir = StockMovement::getCurrentStock($produk->id);
        $this->assertEquals(95, $stokAkhir);
    }

    // ==========================================
    // TEST VALIDASI STOK
    // ==========================================

    /** @test */
    public function dapat_validasi_stok_sebelum_transaksi(): void
    {
        $produk = $this->buatProdukDenganStok('Produk Validasi', 10000, 15000, 20);

        // Validasi untuk quantity yang tersedia
        $valid = $this->inventoryService->validateStockForTransaction([
            ['product_id' => $produk->id, 'quantity' => 15],
        ]);

        $this->assertTrue($valid['valid']);

        // Validasi untuk quantity yang tidak tersedia
        $invalid = $this->inventoryService->validateStockForTransaction([
            ['product_id' => $produk->id, 'quantity' => 25],
        ]);

        $this->assertFalse($invalid['valid']);
        $this->assertCount(1, $invalid['errors']);
    }

    /** @test */
    public function validasi_stok_untuk_multiple_produk(): void
    {
        $produk1 = $this->buatProdukDenganStok('Multi 1', 5000, 8000, 30);
        $produk2 = $this->buatProdukDenganStok('Multi 2', 7000, 10000, 15);

        // Semua tersedia
        $valid = $this->inventoryService->validateStockForTransaction([
            ['product_id' => $produk1->id, 'quantity' => 25],
            ['product_id' => $produk2->id, 'quantity' => 10],
        ]);

        $this->assertTrue($valid['valid']);

        // Produk 2 tidak cukup
        $invalid = $this->inventoryService->validateStockForTransaction([
            ['product_id' => $produk1->id, 'quantity' => 25],
            ['product_id' => $produk2->id, 'quantity' => 20], // Hanya ada 15
        ]);

        $this->assertFalse($invalid['valid']);
    }

    // ==========================================
    // TEST RIWAYAT TRANSAKSI
    // ==========================================

    /** @test */
    public function dapat_melihat_riwayat_transaksi_user(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-001',
            'total_amount' => 50000,
            'total_profit' => 15000,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-002',
            'total_amount' => 75000,
            'total_profit' => 20000,
        ]);

        $transactions = Transaction::where('user_id', $this->user->id)->get();

        $this->assertCount(2, $transactions);
    }

    /** @test */
    public function dapat_melihat_riwayat_transaksi_customer(): void
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-003',
            'total_amount' => 100000,
            'total_profit' => 30000,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-004',
            'total_amount' => 150000,
            'total_profit' => 45000,
        ]);

        $transactions = Transaction::where('customer_id', $this->customer->id)->get();

        $this->assertCount(2, $transactions);

        $totalBelanja = $transactions->sum('total_amount');
        $this->assertEquals(250000, $totalBelanja);
    }

    // ==========================================
    // TEST DISKON DAN PEMBAYARAN
    // ==========================================

    /** @test */
    public function transaksi_dapat_menyimpan_diskon(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-005',
            'total_amount' => 100000,
            'discount' => 10000,
            'total_profit' => 25000,
        ]);

        $this->assertEquals(10000, $transaction->discount);
    }

    /** @test */
    public function transaksi_menyimpan_amount_paid_dan_change(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-006',
            'total_amount' => 85000,
            'amount_paid' => 100000,
            'change_amount' => 15000,
            'total_profit' => 25000,
        ]);

        $this->assertEquals(100000, $transaction->amount_paid);
        $this->assertEquals(15000, $transaction->change_amount);
    }

    // ==========================================
    // TEST PEMBATALAN TRANSAKSI
    // ==========================================

    /** @test */
    public function dapat_membatalkan_transaksi_dan_mengembalikan_stok(): void
    {
        $produk = $this->buatProdukDenganStok('Produk Batal', 10000, 15000, 100);

        $stokAwal = StockMovement::getCurrentStock($produk->id);

        $transaction = $this->buatTransaksi();

        // Simulasi penjualan
        StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -10,
            'unit_price' => 15000,
            'total_price' => 150000,
            'quantity_before' => $stokAwal,
            'quantity_after' => $stokAwal - 10,
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
        ]);

        $stokSetelahJual = StockMovement::getCurrentStock($produk->id);
        $this->assertEquals($stokAwal - 10, $stokSetelahJual);

        // Pembatalan (return stok)
        $currentStock = StockMovement::getCurrentStock($produk->id);
        StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_RETURN,
            'quantity' => 10, // Positif karena return
            'unit_price' => 10000, // Harga beli
            'total_price' => 100000,
            'quantity_before' => $currentStock,
            'quantity_after' => $currentStock + 10,
            'notes' => 'Pembatalan transaksi ' . $transaction->invoice_number,
        ]);

        $stokSetelahBatal = StockMovement::getCurrentStock($produk->id);
        $this->assertEquals($stokAwal, $stokSetelahBatal);
    }

    // ==========================================
    // TEST METODE PEMBAYARAN
    // ==========================================

    /** @test */
    public function transaksi_dapat_menyimpan_metode_pembayaran(): void
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-007',
            'total_amount' => 50000,
            'total_profit' => 15000,
            'payment_method' => 'cash',
        ]);

        $this->assertEquals('cash', $transaction->payment_method);

        $transaction2 = Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-008',
            'total_amount' => 75000,
            'total_profit' => 20000,
            'payment_method' => 'qris',
        ]);

        $this->assertEquals('qris', $transaction2->payment_method);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    private function buatProduk(string $title, int $buyPrice, int $sellPrice): Product
    {
        static $counter = 0;
        $counter++;

        return Product::create([
            'barcode' => 'TRANS' . str_pad($counter, 3, '0', STR_PAD_LEFT),
            'title' => $title,
            'description' => $title . ' deskripsi',
            'category_id' => $this->kategori->id,
            'buy_price' => $buyPrice,
            'sell_price' => $sellPrice,
            'stock' => 0,
        ]);
    }

    private function buatProdukDenganStok(string $title, int $buyPrice, int $sellPrice, int $stock): Product
    {
        $produk = $this->buatProduk($title, $buyPrice, $sellPrice);
        $produk->update(['stock' => $stock]);

        Inventory::create([
            'product_id' => $produk->id,
            'barcode' => $produk->barcode,
            'quantity' => $stock,
        ]);

        // Initial stock movement
        StockMovement::create([
            'product_id' => $produk->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'quantity' => $stock,
            'unit_price' => $buyPrice,
            'total_price' => $stock * $buyPrice,
            'quantity_before' => 0,
            'quantity_after' => $stock,
        ]);

        return $produk;
    }

    private function buatTransaksi(): Transaction
    {
        static $invoiceCounter = 100;
        $invoiceCounter++;

        return Transaction::create([
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-' . date('Ymd') . '-' . $invoiceCounter,
            'total_amount' => 0,
            'total_profit' => 0,
        ]);
    }
}
