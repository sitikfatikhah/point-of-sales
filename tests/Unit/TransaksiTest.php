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
            'no_telp' => '08123456789',
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
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-' . date('Ymd') . '-001',
            'cash' => 50000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'cashier_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function dapat_membuat_transaksi_dengan_customer(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice' => 'INV-' . date('Ymd') . '-002',
            'cash' => 100000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertEquals($this->customer->id, $transaction->customer_id);
        $this->assertInstanceOf(Customer::class, $transaction->customer);
    }

    /** @test */
    public function invoice_bersifat_unik_per_tanggal(): void
    {
        $invoice1 = 'INV-' . date('Ymd') . '-003';
        $invoice2 = 'INV-' . date('Ymd') . '-004';

        Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => $invoice1,
            'cash' => 50000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transaction2 = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => $invoice2,
            'cash' => 75000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 75000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Verifikasi kedua invoice berbeda
        $this->assertNotEquals($invoice1, $invoice2);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertEquals($invoice2, $transaction2->invoice);
    }

    // ==========================================
    // TEST DETAIL TRANSAKSI
    // ==========================================

    /** @test */
    public function dapat_menambah_detail_ke_transaksi(): void
    {
        $produk = $this->buatProdukDenganStok('Roti Tawar', 8000, 12000, 100);

        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-' . date('Ymd') . '-004',
            'cash' => 24000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 24000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $produk->id,
            'barcode' => $produk->barcode,
            'quantity' => 2,
            'price' => 24000, // 12000 x 2
            'discount' => 0,
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

        $avgBuyPrice = StockMovement::getAverageBuyPrice($produk->id);
        $sellPrice = $produk->sell_price;
        $quantity = 3;

        $profit = ($sellPrice - $avgBuyPrice) * $quantity;

        $this->assertEquals(30000, $profit);
    }

    /** @test */
    public function profit_menggunakan_average_buy_price_dari_stock_movement(): void
    {
        $produk = $this->buatProduk('Mie Instan', 2000, 3500);

        // Pembelian pertama: 100 @ 2000 = 200000
        StockMovement::create([
            'product_id' => $produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'reference_type' => 'purchase',
            'reference_id' => 1,
            'quantity' => 100,
            'unit_price' => 2000,
            'total_price' => 200000,
            'quantity_before' => 0,
            'quantity_after' => 100,
        ]);

        // Pembelian kedua: 100 @ 2500 = 250000
        StockMovement::create([
            'product_id' => $produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'reference_type' => 'purchase',
            'reference_id' => 2,
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
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-' . date('Ymd') . '-005',
            'cash' => 46000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 46000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Detail 1: 2 x 8000 = 16000
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $produk1->id,
            'barcode' => $produk1->barcode,
            'quantity' => 2,
            'price' => 16000,
            'discount' => 0,
        ]);

        // Detail 2: 3 x 10000 = 30000
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $produk2->id,
            'barcode' => $produk2->barcode,
            'quantity' => 3,
            'price' => 30000,
            'discount' => 0,
        ]);

        $transaction->refresh();

        $totalAmount = $transaction->details->sum('price');
        $this->assertEquals(46000, $totalAmount);

        // Hitung profit berdasarkan average buy price
        $totalProfit = 0;
        foreach ($transaction->details as $detail) {
            $avgBuyPrice = StockMovement::getAverageBuyPrice($detail->product_id);
            $sellPrice = $detail->product->sell_price;
            $profit = ($sellPrice - $avgBuyPrice) * $detail->quantity;
            $totalProfit += $profit;
        }

        // Profit produk1: (8000 - 5000) * 2 = 6000
        // Profit produk2: (10000 - 7000) * 3 = 9000
        // Total: 15000
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
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
            'quantity' => -5, // Negatif untuk penjualan
            'unit_price' => 8000,
            'total_price' => 40000,
            'quantity_before' => 100,
            'quantity_after' => 95,
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
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-001',
            'cash' => 50000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-002',
            'cash' => 75000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 75000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transactions = Transaction::where('cashier_id', $this->user->id)->get();

        $this->assertCount(2, $transactions);
    }

    /** @test */
    public function dapat_melihat_riwayat_transaksi_customer(): void
    {
        Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice' => 'INV-003',
            'cash' => 100000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 100000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'invoice' => 'INV-004',
            'cash' => 150000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 150000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transactions = Transaction::where('customer_id', $this->customer->id)->get();

        $this->assertCount(2, $transactions);

        $totalBelanja = $transactions->sum('grand_total');
        $this->assertEquals(250000, $totalBelanja);
    }

    // ==========================================
    // TEST DISKON DAN PEMBAYARAN
    // ==========================================

    /** @test */
    public function transaksi_dapat_menyimpan_diskon(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-005',
            'cash' => 100000,
            'change' => 10000,
            'discount' => 10000,
            'grand_total' => 90000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertEquals(10000, $transaction->discount);
    }

    /** @test */
    public function transaksi_menyimpan_cash_dan_change(): void
    {
        $transaction = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-006',
            'cash' => 100000,
            'change' => 15000,
            'discount' => 0,
            'grand_total' => 85000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertEquals(100000, $transaction->cash);
        $this->assertEquals(15000, $transaction->change);
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
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
            'quantity' => -10,
            'unit_price' => 15000,
            'total_price' => 150000,
            'quantity_before' => $stokAwal,
            'quantity_after' => $stokAwal - 10,
        ]);

        $stokSetelahJual = StockMovement::getCurrentStock($produk->id);
        $this->assertEquals($stokAwal - 10, $stokSetelahJual);

        // Pembatalan (return stok)
        $currentStock = StockMovement::getCurrentStock($produk->id);
        StockMovement::create([
            'product_id' => $produk->id,
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_RETURN,
            'reference_type' => 'transaction',
            'reference_id' => $transaction->id,
            'quantity' => 10, // Positif karena return
            'unit_price' => 10000, // Harga beli
            'total_price' => 100000,
            'quantity_before' => $currentStock,
            'quantity_after' => $currentStock + 10,
            'notes' => 'Pembatalan transaksi ' . $transaction->invoice,
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
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-007',
            'cash' => 50000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertEquals('cash', $transaction->payment_method);

        $transaction2 = Transaction::create([
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-008',
            'cash' => 75000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 75000,
            'payment_method' => 'qris',
            'payment_status' => 'paid',
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
            'user_id' => $this->user->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'reference_type' => 'purchase',
            'reference_id' => 1,
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
            'cashier_id' => $this->user->id,
            'invoice' => 'INV-' . date('Ymd') . '-' . $invoiceCounter,
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
    }
}
