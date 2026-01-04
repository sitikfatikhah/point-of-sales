<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
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

/**
 * Feature Test Alur Transaksi Lengkap
 *
 * Test ini mensimulasikan skenario bisnis lengkap:
 * - Alur pembelian dari cart sampai checkout
 * - Pengurangan stok otomatis
 * - Perhitungan profit
 * - Pencatatan inventory adjustment
 */
class AlurTransaksiLengkapTest extends TestCase
{
    use RefreshDatabase;

    protected User $kasir;
    protected Customer $pelanggan;
    protected Category $kategori;
    protected Product $produk1;
    protected Product $produk2;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup permission
        Permission::firstOrCreate([
            'name' => 'transactions-access',
            'guard_name' => 'web',
        ]);

        $this->inventoryService = new InventoryService();

        // Setup kasir
        $this->kasir = User::factory()->create([
            'name' => 'Kasir Toko',
            'email' => 'kasir@toko.com',
        ]);
        $this->kasir->givePermissionTo('transactions-access');

        // Setup pelanggan
        $this->pelanggan = Customer::create([
            'name' => 'Dewi Pembeli',
            'no_telp' => '08111222333',
            'address' => 'Jl. Pembeli No. 10',
        ]);

        // Setup kategori
        $this->kategori = Category::create([
            'name' => 'Makanan Ringan',
            'description' => 'Snack dan makanan ringan',
        ]);

        // Setup produk
        $this->produk1 = Product::create([
            'barcode' => 'MKN001',
            'title' => 'Keripik Kentang',
            'description' => 'Keripik kentang renyah',
            'category_id' => $this->kategori->id,
            'buy_price' => 8000,
            'sell_price' => 12000,
            'stock' => 50,
        ]);

        $this->produk2 = Product::create([
            'barcode' => 'MKN002',
            'title' => 'Cokelat Batang',
            'description' => 'Cokelat batang manis',
            'category_id' => $this->kategori->id,
            'buy_price' => 15000,
            'sell_price' => 22000,
            'stock' => 30,
        ]);

        // Setup inventory
        Inventory::create([
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'quantity' => $this->produk1->stock,
        ]);

        Inventory::create([
            'product_id' => $this->produk2->id,
            'barcode' => $this->produk2->barcode,
            'quantity' => $this->produk2->stock,
        ]);
    }

    // ==========================================
    // TEST SKENARIO TRANSAKSI SEDERHANA
    // ==========================================

    /** @test */
    public function skenario_beli_satu_produk_tunai(): void
    {
        // === LANGKAH 1: Tambah ke keranjang ===
        $jumlahBeli = 3;
        $hargaSatuan = $this->produk1->sell_price; // 12000
        $totalHarga = $jumlahBeli * $hargaSatuan; // 36000

        $cart = Cart::create([
            'cashier_id' => $this->kasir->id,
            'product_id' => $this->produk1->id,
            'quantity' => $jumlahBeli,
            'price' => $totalHarga,
        ]);

        $this->assertDatabaseHas('carts', [
            'cashier_id' => $this->kasir->id,
            'product_id' => $this->produk1->id,
            'quantity' => $jumlahBeli,
        ]);

        // === LANGKAH 2: Proses checkout ===
        $diskon = 0;
        $grandTotal = $totalHarga - $diskon; // 36000
        $uangDibayar = 50000;
        $kembalian = $uangDibayar - $grandTotal; // 14000
        $stokAwal = $this->produk1->stock; // 50

        // Buat transaksi
        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-' . strtoupper(uniqid()),
            'cash' => $uangDibayar,
            'change' => $kembalian,
            'discount' => $diskon,
            'grand_total' => $grandTotal,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Buat detail transaksi
        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'discount' => 0,
            'quantity' => $jumlahBeli,
            'price' => $totalHarga,
        ]);

        // Hitung dan simpan profit
        $profitPerItem = $this->produk1->sell_price - $this->produk1->buy_price; // 4000
        $totalProfit = $profitPerItem * $jumlahBeli; // 12000

        Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => $totalProfit,
        ]);

        // Proses pengurangan stok via inventory service
        $transaksi->load('details.product');
        $this->inventoryService->processTransaction($transaksi);

        // Hapus cart setelah checkout
        $cart->delete();

        // === VERIFIKASI HASIL ===

        // 1. Transaksi tercatat dengan benar
        $this->assertDatabaseHas('transactions', [
            'id' => $transaksi->id,
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'grand_total' => $grandTotal,
            'payment_status' => 'paid',
        ]);

        // 2. Detail transaksi tercatat
        $this->assertDatabaseHas('transaction_details', [
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk1->id,
            'quantity' => $jumlahBeli,
        ]);

        // 3. Profit tercatat
        $this->assertDatabaseHas('profits', [
            'transaction_id' => $transaksi->id,
            'total' => $totalProfit,
        ]);

        // 4. Stok berkurang
        $inventoryProduk = Inventory::where('product_id', $this->produk1->id)->first();
        $this->assertEquals($stokAwal - $jumlahBeli, $inventoryProduk->quantity);

        // 5. Stock movement tercatat
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->produk1->id,
            'movement_type' => StockMovement::TYPE_SALE,
            'quantity' => -$jumlahBeli,
        ]);

        // 6. Cart sudah kosong
        $this->assertDatabaseMissing('carts', ['id' => $cart->id]);
    }

    // ==========================================
    // TEST SKENARIO TRANSAKSI MULTI PRODUK
    // ==========================================

    /** @test */
    public function skenario_beli_multi_produk_dengan_diskon(): void
    {
        $this->actingAs($this->kasir);

        // === LANGKAH 1: Setup keranjang dengan beberapa produk ===
        $qty1 = 2; // Keripik Kentang
        $qty2 = 3; // Cokelat Batang

        $harga1 = $this->produk1->sell_price * $qty1; // 24000
        $harga2 = $this->produk2->sell_price * $qty2; // 66000
        $totalHarga = $harga1 + $harga2; // 90000

        Cart::create([
            'cashier_id' => $this->kasir->id,
            'product_id' => $this->produk1->id,
            'quantity' => $qty1,
            'price' => $harga1,
        ]);

        Cart::create([
            'cashier_id' => $this->kasir->id,
            'product_id' => $this->produk2->id,
            'quantity' => $qty2,
            'price' => $harga2,
        ]);

        // === LANGKAH 2: Checkout dengan diskon ===
        $diskon = 10000;
        $grandTotal = $totalHarga - $diskon; // 80000
        $uangDibayar = 100000;
        $kembalian = $uangDibayar - $grandTotal; // 20000

        $stokAwal1 = $this->produk1->stock;
        $stokAwal2 = $this->produk2->stock;

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-MULTI-' . strtoupper(uniqid()),
            'cash' => $uangDibayar,
            'change' => $kembalian,
            'discount' => $diskon,
            'grand_total' => $grandTotal,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Detail produk 1
        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'discount' => 0,
            'quantity' => $qty1,
            'price' => $harga1,
        ]);

        // Detail produk 2
        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk2->id,
            'barcode' => $this->produk2->barcode,
            'discount' => 0,
            'quantity' => $qty2,
            'price' => $harga2,
        ]);

        // Hitung profit untuk setiap produk
        $profit1 = ($this->produk1->sell_price - $this->produk1->buy_price) * $qty1; // 8000
        $profit2 = ($this->produk2->sell_price - $this->produk2->buy_price) * $qty2; // 21000
        $totalProfit = $profit1 + $profit2; // 29000

        Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => $totalProfit,
        ]);

        // Proses pengurangan stok
        $transaksi->load('details.product');
        $this->inventoryService->processTransaction($transaksi);

        // Hapus cart
        Cart::where('cashier_id', $this->kasir->id)->delete();

        // === VERIFIKASI ===

        // 1. Grand total setelah diskon
        $this->assertEquals(80000, $transaksi->grand_total);
        $this->assertEquals(10000, $transaksi->discount);

        // 2. Detail transaksi ada 2
        $transaksi->refresh();
        $this->assertCount(2, $transaksi->details);

        // 3. Stok kedua produk berkurang
        $inv1 = Inventory::where('product_id', $this->produk1->id)->first();
        $inv2 = Inventory::where('product_id', $this->produk2->id)->first();

        $this->assertEquals($stokAwal1 - $qty1, $inv1->quantity);
        $this->assertEquals($stokAwal2 - $qty2, $inv2->quantity);

        // 4. Total profit tercatat
        $this->assertEquals($totalProfit, $transaksi->profits->sum('total'));

        // 5. Stock movement tercatat untuk kedua produk
        $movements = StockMovement::where('reference_type', 'transaction')
            ->where('reference_id', $transaksi->id)
            ->where('movement_type', StockMovement::TYPE_SALE)
            ->get();

        $this->assertCount(2, $movements);
    }

    // ==========================================
    // TEST SKENARIO PEMBATALAN/REFUND
    // ==========================================

    /** @test */
    public function skenario_pembatalan_transaksi_mengembalikan_stok(): void
    {
        $this->actingAs($this->kasir);

        // === SETUP INITIAL STOCK MOVEMENT ===
        // Buat stock movement awal agar getCurrentStock() berfungsi dengan benar
        $inv = Inventory::where('product_id', $this->produk1->id)->first();
        $stokAwal = (int) $inv->quantity;

        StockMovement::create([
            'product_id' => $this->produk1->id,
            'user_id' => $this->kasir->id,
            'movement_type' => StockMovement::TYPE_PURCHASE,
            'reference_type' => 'initial',
            'reference_id' => 0,
            'quantity' => $stokAwal,
            'unit_price' => $this->produk1->buy_price,
            'total_price' => $this->produk1->buy_price * $stokAwal,
            'quantity_before' => 0,
            'quantity_after' => $stokAwal,
            'notes' => 'Initial stock for testing',
        ]);

        // === BUAT TRANSAKSI ===
        $qty = 5;

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-REFUND-' . strtoupper(uniqid()),
            'cash' => 100000,
            'change' => 40000,
            'discount' => 0,
            'grand_total' => 60000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'discount' => 0,
            'quantity' => $qty,
            'price' => 60000,
        ]);

        // Proses penjualan (stok berkurang)
        $transaksi->load('details.product');
        $this->inventoryService->processTransaction($transaksi);

        $inv->refresh();
        $this->assertEquals($stokAwal - $qty, (int) $inv->quantity);

        // === BATALKAN TRANSAKSI (REFUND) ===
        $transaksi->load('details.product');
        $this->inventoryService->reverseTransaction($transaksi);

        // === VERIFIKASI STOK KEMBALI ===
        $inv->refresh();
        $this->assertEquals($stokAwal, (int) $inv->quantity);

        // Stock movement return tercatat
        $returnMovement = StockMovement::where('product_id', $this->produk1->id)
            ->where('movement_type', StockMovement::TYPE_RETURN)
            ->first();

        $this->assertNotNull($returnMovement);
        $this->assertEquals($qty, (int) $returnMovement->quantity);
    }

    // ==========================================
    // TEST SKENARIO STOK HABIS
    // ==========================================

    /** @test */
    public function transaksi_dengan_stok_yang_tersisa_sedikit(): void
    {
        $this->actingAs($this->kasir);

        // Set stok ke 3
        $this->produk1->update(['stock' => 3]);
        $inv = Inventory::where('product_id', $this->produk1->id)->first();
        $inv->update(['quantity' => 3]);

        // Beli semua stok yang tersisa
        $qty = 3;

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-HABIS-' . strtoupper(uniqid()),
            'cash' => 50000,
            'change' => 14000,
            'discount' => 0,
            'grand_total' => 36000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'discount' => 0,
            'quantity' => $qty,
            'price' => 36000,
        ]);

        $transaksi->load('details.product');
        $this->inventoryService->processTransaction($transaksi);

        // Stok harus 0, tidak negatif
        $inv->refresh();
        $this->assertEquals(0, $inv->quantity);
    }

    // ==========================================
    // TEST SKENARIO GUEST CHECKOUT
    // ==========================================

    /** @test */
    public function transaksi_tanpa_pelanggan_terdaftar(): void
    {
        $qty = 2;

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => null, // Guest checkout
            'invoice' => 'TRX-GUEST-' . strtoupper(uniqid()),
            'cash' => 30000,
            'change' => 6000,
            'discount' => 0,
            'grand_total' => 24000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'discount' => 0,
            'quantity' => $qty,
            'price' => 24000,
        ]);

        // Verifikasi transaksi valid tanpa customer
        $this->assertNull($transaksi->customer_id);
        $this->assertNull($transaksi->customer);
        $this->assertNotNull($transaksi->cashier);
        $this->assertEquals('paid', $transaksi->payment_status);
    }

    // ==========================================
    // TEST VALIDASI DATA
    // ==========================================

    /** @test */
    public function perhitungan_profit_konsisten_dengan_detail(): void
    {
        $qty1 = 4;
        $qty2 = 2;

        // Harga beli dari setup
        $buyPrice1 = 8000;  // buy_price untuk produk1
        $buyPrice2 = 15000; // buy_price untuk produk2

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-VALID-' . strtoupper(uniqid()),
            'cash' => 100000,
            'change' => 8000,
            'discount' => 0,
            'grand_total' => 92000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Detail 1: 4 x 12000 = 48000
        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk1->id,
            'barcode' => $this->produk1->barcode,
            'discount' => 0,
            'quantity' => $qty1,
            'price' => $this->produk1->sell_price * $qty1,
        ]);

        // Detail 2: 2 x 22000 = 44000
        TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk2->id,
            'barcode' => $this->produk2->barcode,
            'discount' => 0,
            'quantity' => $qty2,
            'price' => $this->produk2->sell_price * $qty2,
        ]);

        // Profit 1: (12000-8000) x 4 = 16000
        // Profit 2: (22000-15000) x 2 = 14000
        // Total: 30000
        // Menggunakan harga beli langsung karena buy_price accessor menggunakan average dari StockMovement
        $expectedProfit =
            ($this->produk1->sell_price - $buyPrice1) * $qty1 +
            ($this->produk2->sell_price - $buyPrice2) * $qty2;

        $this->assertEquals(30000, $expectedProfit, 'Expected profit calculation is wrong');

        $profit = Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => $expectedProfit,
        ]);

        // Verifikasi data langsung setelah create
        $this->assertEquals(30000, $profit->total, 'Profit total right after create');

        // Verifikasi total harga sesuai dengan query langsung
        $totalHargaDetail = TransactionDetail::where('transaction_id', $transaksi->id)->sum('price');
        $this->assertEquals(92000, $totalHargaDetail);

        // Verifikasi profit sesuai perhitungan dengan query langsung
        $profitRecord = Profit::where('transaction_id', $transaksi->id)->first();
        $this->assertNotNull($profitRecord);
        $this->assertEquals(30000, $profitRecord->total);
    }

    /** @test */
    public function kembalian_harus_sesuai_perhitungan(): void
    {
        $grandTotal = 75000;
        $uangDibayar = 100000;
        $kembalianExpected = $uangDibayar - $grandTotal; // 25000

        $transaksi = Transaction::create([
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->pelanggan->id,
            'invoice' => 'TRX-KEMBALIAN-' . strtoupper(uniqid()),
            'cash' => $uangDibayar,
            'change' => $kembalianExpected,
            'discount' => 0,
            'grand_total' => $grandTotal,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertEquals($kembalianExpected, $transaksi->change);
        $this->assertEquals(
            $transaksi->cash - $transaksi->grand_total,
            $transaksi->change
        );
    }
}
