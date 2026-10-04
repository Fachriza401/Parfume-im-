<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    private const PRODUCTS = [
        'floral' => [
            ['name' => 'Midnight Floral', 'price' => 275000, 'stock' => 40, 'description' => "Mawar dan melati yang merekah di bawah langit malam, berpadu dengan musk lembut untuk kesan feminin yang tenang.\n\nTop notes: bergamot dan pir. Heart notes: mawar Bulgaria dan melati. Base notes: musk putih dan kayu kasmir.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 6–8 jam.\nSaran pemakaian: makan malam, acara spesial, atau saat ingin tampil anggun."],
            ['name' => 'Rose de Nuit', 'price' => 320000, 'stock' => 25, 'description' => "Mawar merah yang lembut bertemu musk bersih dan sedikit rempah. Romantis tanpa terasa berlebihan.\n\nTop notes: lada merah muda dan mandarin. Heart notes: mawar damask dan peoni. Base notes: musk putih dan amber.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 6–8 jam.\nSaran pemakaian: kencan, acara malam, atau hadiah istimewa."],
            ['name' => 'Jasmine Petal', 'price' => 245000, 'stock' => 60, 'description' => "Kesegaran kelopak melati berpadu dengan bunga jeruk dan sentuhan teh, menghasilkan aroma bersih yang lembut dan elegan.\n\nTop notes: neroli dan teh hijau. Heart notes: melati sambac dan bunga jeruk. Base notes: musk lembut dan kayu putih.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 5–7 jam.\nSaran pemakaian: kantor, aktivitas harian, dan suasana santai."],
        ],
        'woody' => [
            ['name' => 'Oud Noir', 'price' => 495000, 'stock' => 15, 'description' => "Oud gelap yang kaya bertemu cendana halus dan dupa, menghadirkan karakter berani dengan jejak aroma yang dalam.\n\nTop notes: kapulaga dan lada hitam. Heart notes: kayu oud dan dupa. Base notes: cendana, amber, dan patchouli.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 8–10 jam.\nSaran pemakaian: malam hari, acara formal, atau cuaca sejuk."],
            ['name' => 'Cedar Woods', 'price' => 350000, 'stock' => 30, 'description' => "Cedar aromatik dengan daun violet dan vetiver. Kesan hangat, rapi, dan membumi untuk keseharian.\n\nTop notes: bergamot dan daun violet. Heart notes: cedar dan sage. Base notes: vetiver, oakmoss, dan musk.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 6–8 jam.\nSaran pemakaian: kantor, pertemuan, dan aktivitas luar ruang."],
            ['name' => 'Sandalwood Dusk', 'price' => 410000, 'stock' => 20, 'description' => "Cendana creamy yang nyaman berpadu dengan vanila dan sedikit rempah, seperti hangatnya senja yang menenangkan.\n\nTop notes: jahe dan bergamot. Heart notes: cendana dan iris. Base notes: vanila, tonka, dan musk.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 7–9 jam.\nSaran pemakaian: malam santai, perjalanan, atau momen relaksasi."],
        ],
        'fresh' => [
            ['name' => 'Ocean Breeze', 'price' => 225000, 'stock' => 50, 'description' => "Angin laut yang bersih bertemu citrus dan aroma akuatik ringan. Segar dan mudah digunakan sepanjang hari.\n\nTop notes: lemon dan sea salt. Heart notes: marine accord dan lavender. Base notes: driftwood dan musk.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 5–7 jam.\nSaran pemakaian: siang hari, aktivitas luar ruang, dan cuaca tropis."],
            ['name' => 'Green Forest', 'price' => 265000, 'stock' => 45, 'description' => "Aroma daun hijau dan tanah setelah hujan, ditemani citrus lembut dan kayu yang bersih.\n\nTop notes: petitgrain dan daun mint. Heart notes: teh hijau dan geranium. Base notes: vetiver, cedar, dan musk.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 5–7 jam.\nSaran pemakaian: siang hari, kegiatan aktif, dan suasana santai."],
        ],
        'oriental' => [
            ['name' => 'Amber Palace', 'price' => 545000, 'stock' => 12, 'description' => "Amber keemasan yang hangat, dipeluk vanila dan rempah lembut untuk kesan mewah dan memikat.\n\nTop notes: saffron dan mandarin. Heart notes: amber dan mawar. Base notes: vanila, benzoin, dan musk.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 8–10 jam.\nSaran pemakaian: acara malam, pesta, atau momen spesial."],
            ['name' => 'Sultan Oud', 'price' => 750000, 'stock' => 8, 'description' => "Oud berkarakter dengan mawar, rempah, dan resin yang kaya. Racikan premium dengan aura hangat dan jejak yang tahan lama.\n\nTop notes: saffron dan kapulaga. Heart notes: mawar damask dan oud. Base notes: amber, resin, dan cendana.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 8–12 jam.\nSaran pemakaian: malam hari, acara formal, dan koleksi pencinta oud."],
        ],
        'citrus' => [
            ['name' => 'Lemon Spark', 'price' => 195000, 'stock' => 70, 'description' => "Lemon dan jeruk nipis yang cerah memberi semangat, diimbangi sedikit jahe dan musk yang ringan.\n\nTop notes: lemon Sisilia dan jeruk nipis. Heart notes: jahe dan neroli. Base notes: musk putih dan cedar.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 4–6 jam.\nSaran pemakaian: pagi hari, setelah berolahraga, dan cuaca panas."],
            ['name' => 'Bergamot Glow', 'price' => 235000, 'stock' => 55, 'description' => "Bergamot segar berpadu dengan teh hijau dan bunga yang lembut. Cerah, bersih, dan terasa menenangkan.\n\nTop notes: bergamot Calabria dan yuzu. Heart notes: teh hijau dan freesia. Base notes: white musk dan kayu ringan.\nKonsentrasi Eau de Parfum · 50 ml · Tahan aroma sekitar 5–7 jam.\nSaran pemakaian: kantor, siang hari, dan pemakaian harian."],
        ],
    ];

    public function run(): void
    {
        $categories = Category::pluck('id', 'slug');

        foreach (self::PRODUCTS as $slug => $products) {
            $categoryId = $categories[$slug] ?? null;

            if ($categoryId === null) {
                continue;
            }

            foreach ($products as $product) {
                Product::updateOrCreate(
                    ['slug' => Str::slug($product['name'])],
                    [
                        'category_id' => $categoryId,
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'image_url' => '/images/products/'.Str::slug($product['name']).'.png',
                        'price' => $product['price'],
                        'stock' => $product['stock'],
                        'rating_avg' => 0,
                        'rating_count' => 0,
                        'sold_count' => random_int(0, 120),
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->seedPromotions();
    }

    private function seedPromotions(): void
    {
        $promotions = [
            [
                'code' => 'PARFUM10',
                'name' => 'Diskon 10%',
                'description' => 'Potongan 10% untuk seluruh produk.',
                'discount_type' => DiscountType::PERCENTAGE,
                'discount_value' => 10,
                'max_discount_amount' => 100000,
                'min_order_amount' => 100000,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'NOBARU25',
                'name' => 'Diskon 25%',
                'description' => 'Potongan 25% untuk produk baru.',
                'discount_type' => DiscountType::PERCENTAGE,
                'discount_value' => 25,
                'max_discount_amount' => 200000,
                'min_order_amount' => 300000,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonth(),
                'is_active' => true,
            ],
            [
                'code' => 'HEMAT50K',
                'name' => 'Potongan Rp50.000',
                'description' => 'Potongan tetap Rp50.000.',
                'discount_type' => DiscountType::FIXED,
                'discount_value' => 50000,
                'max_discount_amount' => null,
                'min_order_amount' => 200000,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(2),
                'is_active' => true,
            ],
            [
                'code' => 'EXPIRED',
                'name' => 'Promo Kedaluwarsa',
                'description' => 'Promo contoh yang sudah berakhir.',
                'discount_type' => DiscountType::PERCENTAGE,
                'discount_value' => 99,
                'max_discount_amount' => null,
                'min_order_amount' => 0,
                'starts_at' => now()->subMonths(3),
                'ends_at' => now()->subDay(),
                'is_active' => true,
            ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::updateOrCreate(
                ['code' => $promotion['code']],
                $promotion + ['usage_limit' => null, 'used_count' => 0]
            );
        }
    }
}
