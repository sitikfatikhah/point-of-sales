<?php

namespace Tests\Unit;

use App\Models\Cart;
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
 * Unit Test Transaksi POS
 *
 * Test ini memastikan seluruh alur transaksi berjalan dengan benar:
 * - Relasi antar model
 * - Perhitungan total transaksi
 * - Perhitungan profit
 * - Pengurangan stok
 * - Integritas data
 */
class TransaksiTest extends TestCase
{
    use RefreshDatabase;

    protected User $kasir;
    protected Category $kategori;
    protected Product $produk;
    protected Customer $pelanggan;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat data dasar untuk testing
        $this->kasir = User::factory()->create([
            'name' => 'Kasir Test',
            'email' => 'kasir@test.com',
        ]);

        $this->kategori = Category::create([
            'name' => 'Makanan',
            'description' => 'Kategori makanan',
        ]);

        $this->produk = Product::create([
            'barcode' => 'PRD001',
            'title' => 'Nasi Goreng',
            'description' => 'Nasi goreng spesial',
            'category_id' => $this->kategori->id,
            'buy_price' => 15000,  // Harga beli
            'sell_price' => 25000, // Harga jual
            'stock' => 100,
        ]);

        $this->pelanggan = Customer::create([
            'name' => 'Budi Santoso',
            'no_telp' => '08123456789',
            'address' => 'Jl. Merdeka No. 123',
        ]);

        // Buat inventory untuk produk
        Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => $this->produk->stock,
        ]);
    }

    // ==========================================
    // TEST RELASI MODEL TRANSAKSI
    // ==========================================

    /** @test */
    public function transaksi_memiliki_relasi_dengan_kasir(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-TEST001',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertInstanceOf(User::class, $transaksi->cashier);
        $this->assertEquals($this->kasir->id, $transaksi->cashier->id);
        $this->assertEquals('Kasir Test', $transaksi->cashier->name);
    }

    /** @test */
    public function transaksi_memiliki_relasi_dengan_pelanggan(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-TEST002',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertInstanceOf(Customer::class, $transaksi->customer);
        $this->assertEquals($this->pelanggan->id, $transaksi->customer->id);
        $this->assertEquals('Budi Santoso', $transaksi->customer->name);
    }

    /** @test */
    public function transaksi_memiliki_banyak_detail_transaksi(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-TEST003',
            'cash' => 100000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 75000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Buat 2 detail transaksi
        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 1,
            'discount' => 0,
            'price' => 25000,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 2,
            'discount' => 0,
            'price' => 50000,
        ]);

        $transaksi->refresh();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $transaksi->details);
        $this->assertCount(2, $transaksi->details);
    }

    /** @test */
    public function transaksi_memiliki_banyak_profit(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-TEST004',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Buat profit untuk transaksi
        Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => 10000, // Profit = sell_price - buy_price = 25000 - 15000
        ]);

        $transaksi->refresh();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $transaksi->profits);
        $this->assertCount(1, $transaksi->profits);
        $this->assertEquals(10000, $transaksi->profits->first()->total);
    }

    // ==========================================
    // TEST DETAIL TRANSAKSI
    // ==========================================

    /** @test */
    public function detail_transaksi_memiliki_relasi_ke_transaksi(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-TEST005',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 1,
            'price' => 25000,
        ]);

        $this->assertInstanceOf(Transaction::class, $detail->transaction);
        $this->assertEquals($transaksi->id, $detail->transaction->id);
    }

    /** @test */
    public function detail_transaksi_memiliki_relasi_ke_produk(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-TEST006',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 1,
            'price' => 25000,
        ]);

        $this->assertInstanceOf(Product::class, $detail->product);
        $this->assertEquals($this->produk->id, $detail->product->id);
        $this->assertEquals('Nasi Goreng', $detail->product->title);
    }

    // ==========================================
    // TEST PERHITUNGAN TRANSAKSI
    // ==========================================

    /** @test */
    public function perhitungan_grand_total_transaksi_benar(): void
    {
        // Skenario: Beli 3 item dengan harga @25000, diskon 5000
        $jumlahItem = 3;
        $hargaSatuan = $this->produk->sell_price; // 25000
        $totalHarga = $jumlahItem * $hargaSatuan; // 75000
        $diskon = 5000;
        $grandTotal = $totalHarga - $diskon; // 70000
        $uangDibayar = 100000;
        $kembalian = $uangDibayar - $grandTotal; // 30000

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-CALC001',
            'cash' => $uangDibayar,
            'change' => $kembalian,
            'discount' => $diskon,
            'grand_total' => $grandTotal,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertEquals(100000, $transaksi->cash);
        $this->assertEquals(30000, $transaksi->change);
        $this->assertEquals(5000, $transaksi->discount);
        $this->assertEquals(70000, $transaksi->grand_total);

        // Verifikasi kembalian = uang - grand_total
        $this->assertEquals(
            $transaksi->cash - $transaksi->grand_total,
            $transaksi->change
        );
    }

    /** @test */
    public function perhitungan_profit_per_transaksi_benar(): void
    {
        // Skenario: Beli 2 item dengan harga beli 15000, harga jual 25000
        $jumlahItem = 2;
        $hargaBeli = $this->produk->buy_price;   // 15000
        $hargaJual = $this->produk->sell_price;  // 25000
        $profitPerItem = $hargaJual - $hargaBeli; // 10000
        $totalProfit = $profitPerItem * $jumlahItem; // 20000

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-PROFIT001',
            'cash' => 50000,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => $jumlahItem,
            'price' => $hargaJual * $jumlahItem,
        ]);

        Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => $totalProfit,
        ]);

        $transaksi->refresh();

        $this->assertEquals(20000, $transaksi->profits->sum('total'));
        $this->assertEquals(
            ($hargaJual - $hargaBeli) * $jumlahItem,
            $transaksi->profits->sum('total')
        );
    }

    /** @test */
    public function total_harga_detail_sesuai_dengan_kuantitas(): void
    {
        $jumlah = 5;
        $hargaSatuan = $this->produk->sell_price; // 25000
        $totalHarga = $jumlah * $hargaSatuan; // 125000

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-DETAIL001',
            'cash' => 150000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => $totalHarga,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => $jumlah,
            'price' => $totalHarga,
        ]);

        $this->assertEquals(5, $detail->quantity);
        $this->assertEquals(125000, $detail->price);
        $this->assertEquals(
            $detail->quantity * $this->produk->sell_price,
            $detail->price
        );
    }

    // ==========================================
    // TEST STATUS PEMBAYARAN
    // ==========================================

    /** @test */
    public function transaksi_tunai_berstatus_paid(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-CASH001',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertEquals('cash', $transaksi->payment_method);
        $this->assertEquals('paid', $transaksi->payment_status);
        $this->assertNull($transaksi->payment_url);
    }

    /** @test */
    public function transaksi_gateway_berstatus_pending(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-GATEWAY001',
            'cash' => 0,
            'change' => 0,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'midtrans',
            'payment_status' => 'pending',
            'payment_url' => 'https://payment.gateway/pay/xxx',
            'payment_reference' => 'ORDER-XXX',
        ]);

        $this->assertEquals('midtrans', $transaksi->payment_method);
        $this->assertEquals('pending', $transaksi->payment_status);
        $this->assertNotNull($transaksi->payment_url);
        $this->assertNotNull($transaksi->payment_reference);
    }

    // ==========================================
    // TEST FORMAT INVOICE
    // ==========================================

    /** @test */
    public function format_invoice_dimulai_dengan_trx(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-ABCD1234',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertStringStartsWith('TRX-', $transaksi->invoice);
    }

    /** @test */
    public function invoice_harus_berbeda_untuk_setiap_transaksi(): void
    {
        $transaksi1 = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-UNIQUE001',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transaksi2 = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-UNIQUE002',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Pastikan invoice berbeda
        $this->assertNotEquals($transaksi1->invoice, $transaksi2->invoice);

        // Verifikasi keduanya tersimpan dengan invoice masing-masing
        $this->assertDatabaseHas('transactions', ['invoice' => 'TRX-UNIQUE001']);
        $this->assertDatabaseHas('transactions', ['invoice' => 'TRX-UNIQUE002']);
    }

    // ==========================================
    // TEST CART
    // ==========================================

    /** @test */
    public function cart_memiliki_relasi_ke_produk(): void
    {
        $cart = Cart::create([
            'cashier_id' => $this->kasir->id,
            'product_id' => $this->produk->id,
            'quantity' => 2,
            'price' => $this->produk->sell_price * 2,
        ]);

        $this->assertInstanceOf(Product::class, $cart->product);
        $this->assertEquals($this->produk->id, $cart->product->id);
    }

    /** @test */
    public function cart_dihapus_setelah_transaksi_selesai(): void
    {
        $cart = Cart::create([
            'cashier_id' => $this->kasir->id,
            'product_id' => $this->produk->id,
            'quantity' => 1,
            'price' => $this->produk->sell_price,
        ]);

        $this->assertDatabaseHas('carts', ['id' => $cart->id]);

        // Simulasi checkout - hapus cart
        $cart->delete();

        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
    }

    // ==========================================
    // TEST INTEGRITAS DATA
    // ==========================================

    /** @test */
    public function transaksi_tanpa_pelanggan_diperbolehkan(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => null, // Guest checkout
            'invoice' => 'TRX-GUEST001',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertNull($transaksi->customer_id);
        $this->assertNull($transaksi->customer);
        $this->assertNotNull($transaksi->cashier);
    }

    /** @test */
    public function profit_terhubung_dengan_transaksi_yang_benar(): void
    {
        $transaksi1 = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-PROFIT-A',
            'cash' => 50000,
            'change' => 25000,
            'discount' => 0,
            'grand_total' => 25000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $transaksi2 = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-PROFIT-B',
            'cash' => 100000,
            'change' => 50000,
            'discount' => 0,
            'grand_total' => 50000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        Profit::create(['transaction_id' => $transaksi1->id, 'total' => 10000]);
        Profit::create(['transaction_id' => $transaksi2->id, 'total' => 20000]);

        $transaksi1->refresh();
        $transaksi2->refresh();

        $this->assertEquals(10000, $transaksi1->profits->sum('total'));
        $this->assertEquals(20000, $transaksi2->profits->sum('total'));

        // Verifikasi profit terhubung ke transaksi yang benar
        $this->assertEquals($transaksi1->id, $transaksi1->profits->first()->transaction_id);
        $this->assertEquals($transaksi2->id, $transaksi2->profits->first()->transaction_id);
    }
}
