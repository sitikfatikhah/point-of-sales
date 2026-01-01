<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Profit;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        Cart::truncate();
        TransactionDetail::truncate();
        Profit::truncate();
        Transaction::truncate();
        Product::truncate();
        Category::truncate();
        Customer::truncate();

        Schema::enableForeignKeyConstraints();

        $placeholders = $this->ensurePlaceholderImages();

        $customers = $this->seedCustomers();
        $categories = $this->seedCategories($placeholders['category']);
        $products = $this->seedProducts($categories, $placeholders['product']);

        $this->seedTransactions($customers, $products);
    }

    private function ensurePlaceholderImages(): array
    {
        $source = public_path('assets/photo/auth.jpg');

        $categoryFile = 'sample-category.jpg';
        $productFile = 'sample-product.jpg';

        if (file_exists($source)) {
            if (!Storage::disk('public')->exists('category/' . $categoryFile)) {
                Storage::disk('public')->put('category/' . $categoryFile, file_get_contents($source));
            }

            if (!Storage::disk('public')->exists('products/' . $productFile)) {
                Storage::disk('public')->put('products/' . $productFile, file_get_contents($source));
            }
        }

        return [
            'category' => $categoryFile,
            'product' => $productFile,
        ];
    }

    private function seedCustomers(): Collection
    {
        $customers = collect([
            ['name' => 'No Name', 'no_telp' => '0000000000', 'address' => 'no address'],
        ]);

        return $customers
            ->map(fn($customer) => Customer::create($customer))
            ->keyBy('name');
    }

    private function seedCategories(string $image): Collection
    {
        $categories = collect([
            ['name' => 'Beverages', 'description' => 'Aneka minuman kemasan dingin dan panas'],
            ['name' => 'Snacks', 'description' => 'Camilan kemasan siap saji'],
            ['name' => 'Fresh Produce', 'description' => 'Buah dan sayuran segar pilihan'],
            ['name' => 'Household', 'description' => 'Kebutuhan rumah tangga harian'],
            ['name' => 'Personal Care', 'description' => 'Produk kebersihan dan perawatan diri'],
        ]);

        return $categories
            ->map(fn($category) => Category::create([
                'name' => $category['name'],
                'description' => $category['description'],
                'image' => $image,
            ]))
            ->keyBy('name');
    }

    private function seedProducts(Collection $categories, string $image): Collection
    {
        $products = collect([
            ['category' => 'Beverages', 'barcode' => 'BRG-0001', 'title' => 'Cold Brew Coffee 250ml', 'description' => 'Kopi Arabica rumahan dengan rasa manis alami.', 'buy_price' => 25000, 'sell_price' => 35000, 'stock' => 80],
            ['category' => 'Beverages', 'barcode' => 'BRG-0002', 'title' => 'Thai Tea Literan', 'description' => 'Thai tea original dengan susu kental manis.', 'buy_price' => 30000, 'sell_price' => 42000, 'stock' => 60],
            ['category' => 'Snacks', 'barcode' => 'BRG-0003', 'title' => 'Keripik Singkong Balado', 'description' => 'Keripik singkong renyah rasa balado pedas manis.', 'buy_price' => 12000, 'sell_price' => 18000, 'stock' => 150],
            ['category' => 'Snacks', 'barcode' => 'BRG-0004', 'title' => 'Granola Bar Cokelat', 'description' => 'Granola bar sehat dengan kacang-kacangan premium.', 'buy_price' => 15000, 'sell_price' => 22000, 'stock' => 100],
            ['category' => 'Fresh Produce', 'barcode' => 'BRG-0005', 'title' => 'Paket Salad Buah', 'description' => 'Campuran buah segar potong siap saji.', 'buy_price' => 20000, 'sell_price' => 32000, 'stock' => 70],
            ['category' => 'Fresh Produce', 'barcode' => 'BRG-0006', 'title' => 'Sayur Organik Mix', 'description' => 'Paket kangkung, bayam, dan selada organik.', 'buy_price' => 18000, 'sell_price' => 27000, 'stock' => 90],
            ['category' => 'Household', 'barcode' => 'BRG-0007', 'title' => 'Sabun Cair Lemon 1L', 'description' => 'Sabun cair anti bakteri aroma lemon segar.', 'buy_price' => 22000, 'sell_price' => 32000, 'stock' => 110],
            ['category' => 'Household', 'barcode' => 'BRG-0008', 'title' => 'Tisu Dapur 2 Ply', 'description' => 'Tisu dapur serbaguna dua lapis.', 'buy_price' => 9000, 'sell_price' => 15000, 'stock' => 200],
            ['category' => 'Personal Care', 'barcode' => 'BRG-0009', 'title' => 'Hand Sanitizer 250ml', 'description' => 'Hand sanitizer food grade non lengket.', 'buy_price' => 17000, 'sell_price' => 25000, 'stock' => 140],
            ['category' => 'Personal Care', 'barcode' => 'BRG-0010', 'title' => 'Shampoo Botani 500ml', 'description' => 'Shampoo botani untuk semua jenis rambut.', 'buy_price' => 28000, 'sell_price' => 40000, 'stock' => 95],
        ]);

        return $products
            ->map(function ($product) use ($categories, $image) {
                $category = $categories->get($product['category']);

                return Product::create([
                    'category_id' => $category?->id,
                    'image' => $image,
                    'barcode' => $product['barcode'],
                    'title' => $product['title'],
                    'description' => $product['description'],
                    'buy_price' => $product['buy_price'],
                    'sell_price' => $product['sell_price'],
                    'stock' => $product['stock'],
                ]);
            })
            ->keyBy('barcode');
    }

    private function seedTransactions(Collection $customers, Collection $products): void
    {
        $cashier = User::where('email', 'cashier@gmail.com')->first() ?? User::first();

        if (!$cashier) {
            return;
        }

        $blueprints = [
            [
                'customer' => 'No Name',
                'discount' => 5000,
                'cash' => 200000,
                'items' => [
                    ['barcode' => 'BRG-0001', 'quantity' => 2],
                    ['barcode' => 'BRG-0003', 'quantity' => 3],
                ],
            ],
            [
                'customer' => 'No Name',
                'discount' => 0,
                'cash' => 150000,
                'items' => [
                    ['barcode' => 'BRG-0005', 'quantity' => 2],
                    ['barcode' => 'BRG-0009', 'quantity' => 1],
                ],
            ],
            [
                'customer' => 'No Name',
                'discount' => 10000,
                'cash' => 180000,
                'items' => [
                    ['barcode' => 'BRG-0007', 'quantity' => 2],
                    ['barcode' => 'BRG-0008', 'quantity' => 4],
                    ['barcode' => 'BRG-0010', 'quantity' => 1],
                ],
            ],
            [
                'customer' => null,
                'discount' => 0,
                'cash' => 75000,
                'items' => [
                    ['barcode' => 'BRG-0004', 'quantity' => 1],
                    ['barcode' => 'BRG-0006', 'quantity' => 1],
                ],
            ],
        ];

        foreach ($blueprints as $blueprint) {
            $customer = $blueprint['customer']
                ? $customers->get($blueprint['customer'])
                : null;

            $items = collect($blueprint['items'])
                ->map(function ($item) use ($products) {
                    $product = $products->get($item['barcode']);

                    if (!$product) {
                        return null;
                    }

                    $lineTotal = $product->sell_price * $item['quantity'];

                    return [
                        'product' => $product,
                        'quantity' => $item['quantity'],
                        'line_total' => $lineTotal,
                        'profit' => ($product->sell_price - $product->buy_price) * $item['quantity'],
                    ];
                })
                ->filter();

            if ($items->isEmpty()) {
                continue;
            }

            $discount = max(0, $blueprint['discount']);
            $gross = $items->sum('line_total');
            $grandTotal = max(0, $gross - $discount);
            $cashPaid = max($grandTotal, $blueprint['cash']);
            $change = $cashPaid - $grandTotal;

            $transaction = Transaction::create([
                'cashier_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'invoice' => 'TRX-' . Str::upper(Str::random(8)),
                'cash' => $cashPaid,
                'change' => $change,
                'discount' => $discount,
                'grand_total' => $grandTotal,
            ]);

            foreach ($items as $item) {
                $transaction->details()->create([
                    'product_id' => $item['product']->id,
                    'barcode'    => $item['product']->barcode,
                    'quantity'   => $item['quantity'],
                    'price'      => $item['line_total'],
                    'discount'   => 0,
                ]);

                $transaction->profits()->create([
                    'total' => $item['profit'],
                ]);

                $item['product']->decrement('stock', $item['quantity']);
            }
        }
    }
}
