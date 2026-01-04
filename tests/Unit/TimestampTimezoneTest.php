<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Profit;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Test Timestamp dan Timezone
 *
 * Test ini memastikan timestamp menggunakan timezone Asia/Jakarta dengan benar:
 * - Format timestamp ISO 8601 dengan offset +07:00
 * - Konsistensi antara model dan database
 * - Trait HasFormattedTimestamps berfungsi
 */
class TimestampTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Category $kategori;
    protected Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan timezone sudah diset
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setLocale('id');

        $this->user = User::factory()->create([
            'name' => 'Test User Timestamp',
        ]);

        $this->kategori = Category::create([
            'name' => 'Kategori Timestamp',
            'description' => 'Test timestamp',
        ]);

        $this->produk = Product::create([
            'barcode' => 'TST001',
            'title' => 'Produk Test Timestamp',
            'description' => 'Untuk testing timestamp',
            'category_id' => $this->kategori->id,
            'buy_price' => 10000,
            'sell_price' => 15000,
            'stock' => 50,
        ]);
    }

    // ==========================================
    // TEST TIMEZONE CONFIGURATION
    // ==========================================

    /** @test */
    public function aplikasi_menggunakan_timezone_asia_jakarta(): void
    {
        $timezone = config('app.timezone');

        $this->assertEquals('Asia/Jakarta', $timezone);
    }

    /** @test */
    public function carbon_now_menggunakan_timezone_asia_jakarta(): void
    {
        $now = Carbon::now();

        $this->assertEquals('Asia/Jakarta', $now->timezone->getName());
    }

    /** @test */
    public function php_date_default_timezone_adalah_asia_jakarta(): void
    {
        $timezone = date_default_timezone_get();

        // Bisa Asia/Jakarta atau WIB
        $this->assertContains($timezone, ['Asia/Jakarta', 'WIB']);
    }

    // ==========================================
    // TEST FORMAT TIMESTAMP
    // ==========================================

    /** @test */
    public function created_at_mengembalikan_format_iso8601_dengan_offset(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS001',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $createdAt = $transaksi->created_at;

        // Format harus ISO 8601 dengan offset +07:00
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/',
            $createdAt,
            'Timestamp harus dalam format ISO 8601 dengan offset +07:00'
        );
    }

    /** @test */
    public function updated_at_mengembalikan_format_iso8601_dengan_offset(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS002',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        // Update record
        $transaksi->update(['discount' => 1000]);
        $transaksi->refresh();

        $updatedAt = $transaksi->updated_at;

        // Format harus ISO 8601 dengan offset +07:00
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/',
            $updatedAt,
            'Timestamp harus dalam format ISO 8601 dengan offset +07:00'
        );
    }

    // ==========================================
    // TEST TRAIT ACCESSOR
    // ==========================================

    /** @test */
    public function created_at_carbon_mengembalikan_instance_carbon(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS003',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $carbonInstance = $transaksi->created_at_carbon;

        $this->assertInstanceOf(Carbon::class, $carbonInstance);
        $this->assertEquals('Asia/Jakarta', $carbonInstance->timezone->getName());
    }

    /** @test */
    public function formatted_created_at_mengembalikan_format_indonesia(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS004',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $formatted = $transaksi->formatted_created_at;

        // Format: "04 Jan 2026 10:30:00"
        $this->assertMatchesRegularExpression(
            '/^\d{2} \w{3} \d{4} \d{2}:\d{2}:\d{2}$/',
            $formatted,
            'Format harus "dd MMM yyyy HH:mm:ss"'
        );
    }

    // ==========================================
    // TEST KONSISTENSI ANTAR MODEL
    // ==========================================

    /** @test */
    public function semua_model_menggunakan_format_timestamp_yang_sama(): void
    {
        $customer = Customer::create([
            'name' => 'Customer Timestamp Test',
            'no_telp' => '08111222333',
            'address' => 'Jl. Test Timestamp',
        ]);

        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => $customer->id,
            'invoice' => 'TRX-TS005',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $profit = Profit::create([
            'transaction_id' => $transaksi->id,
            'total' => 5000,
        ]);

        // Semua harus memiliki offset +07:00
        $this->assertStringContains('+07:00', $this->kategori->created_at);
        $this->assertStringContains('+07:00', $this->produk->created_at);
        $this->assertStringContains('+07:00', $customer->created_at);
        $this->assertStringContains('+07:00', $transaksi->created_at);
        $this->assertStringContains('+07:00', $profit->created_at);
        $this->assertStringContains('+07:00', $this->user->created_at);
    }

    /** @test */
    public function inventory_timestamp_konsisten(): void
    {
        $inventory = Inventory::create([
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'quantity' => 50,
        ]);

        $this->assertStringContains('+07:00', $inventory->created_at);
        $this->assertStringContains('+07:00', $inventory->updated_at);
    }

    // ==========================================
    // TEST TIMESTAMP TIDAK BERUBAH SAAT QUERY
    // ==========================================

    /** @test */
    public function timestamp_tetap_konsisten_setelah_query_ulang(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS006',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $timestampAwal = $transaksi->created_at;

        // Query ulang dari database
        $transaksiDariDb = Transaction::find($transaksi->id);

        $this->assertEquals($timestampAwal, $transaksiDariDb->created_at);
    }

    /** @test */
    public function carbon_instance_dapat_dimanipulasi(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS007',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $carbon = $transaksi->created_at_carbon;

        // Test manipulasi Carbon
        $besok = $carbon->copy()->addDay();
        $kemarin = $carbon->copy()->subDay();

        $this->assertTrue($besok->isAfter($carbon));
        $this->assertTrue($kemarin->isBefore($carbon));

        // Timezone tetap Asia/Jakarta setelah manipulasi
        $this->assertEquals('Asia/Jakarta', $besok->timezone->getName());
        $this->assertEquals('Asia/Jakarta', $kemarin->timezone->getName());
    }

    // ==========================================
    // TEST TIDAK ADA SELISIH -7 JAM
    // ==========================================

    /** @test */
    public function timestamp_tidak_berbeda_7_jam_dari_waktu_sekarang(): void
    {
        $sebelumCreate = Carbon::now('Asia/Jakarta');

        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS008',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $setelahCreate = Carbon::now('Asia/Jakarta');
        $timestampTransaksi = $transaksi->created_at_carbon;

        // Selisih waktu harus kurang dari 5 detik (bukan 7 jam!)
        $selisihDenganSebelum = abs($timestampTransaksi->diffInSeconds($sebelumCreate));
        $selisihDenganSetelah = abs($timestampTransaksi->diffInSeconds($setelahCreate));

        $this->assertLessThan(
            5,
            $selisihDenganSebelum,
            'Timestamp transaksi tidak boleh berbeda jauh dari waktu pembuatan'
        );

        // Pastikan BUKAN selisih 7 jam (25200 detik)
        $tujuhJamDalamDetik = 7 * 60 * 60; // 25200
        $this->assertNotEquals(
            $tujuhJamDalamDetik,
            $selisihDenganSebelum,
            'Timestamp tidak boleh berbeda 7 jam (masalah timezone UTC vs Asia/Jakarta)'
        );
    }

    /** @test */
    public function jam_di_timestamp_sesuai_dengan_waktu_lokal(): void
    {
        $waktuLokal = Carbon::now('Asia/Jakarta');

        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS009',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $timestampCarbon = $transaksi->created_at_carbon;

        // Jam harus sama (dalam toleransi 1 menit)
        $selisihMenit = abs($timestampCarbon->diffInMinutes($waktuLokal));

        $this->assertLessThanOrEqual(
            1,
            $selisihMenit,
            'Jam di timestamp harus sesuai waktu lokal Asia/Jakarta'
        );
    }

    // ==========================================
    // TEST RELASI DENGAN TIMESTAMP
    // ==========================================

    /** @test */
    public function relasi_transaksi_detail_memiliki_timestamp_konsisten(): void
    {
        $transaksi = Transaction::create([
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'invoice' => 'TRX-TS010',
            'cash' => 50000,
            'change' => 35000,
            'discount' => 0,
            'grand_total' => 15000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $detail = TransactionDetail::create([
            'transaction_id' => $transaksi->id,
            'product_id' => $this->produk->id,
            'barcode' => $this->produk->barcode,
            'discount' => 0,
            'quantity' => 1,
            'price' => 15000,
        ]);

        // Timestamp transaksi dan detail harus keduanya +07:00
        $this->assertStringContains('+07:00', $transaksi->created_at);
        $this->assertStringContains('+07:00', $detail->created_at);

        // Waktu pembuatan harus hampir sama
        $selisih = abs(
            $transaksi->created_at_carbon->diffInSeconds($detail->created_at_carbon)
        );

        $this->assertLessThan(5, $selisih);
    }

    /**
     * Custom assertion untuk string contains
     */
    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
