<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * @var array<int, array<string, string>>
     */
    private const CATEGORIES = [
        ['name' => 'Floral', 'slug' => 'floral', 'description' => 'Koleksi wewangian bunga yang romantis dan elegan, dengan karakter mawar, melati, dan kelopak segar. Cocok untuk menemani momen istimewa maupun keseharian.'],
        ['name' => 'Woody', 'slug' => 'woody', 'description' => 'Aroma hangat dan berkarakter dari kayu cedar, cendana, dan oud. Pilihan menenangkan dengan kesan elegan yang melekat.'],
        ['name' => 'Fresh', 'slug' => 'fresh', 'description' => 'Wewangian bersih dan ringan yang terinspirasi dari udara laut, dedaunan, dan kesejukan setelah hujan. Nyaman untuk digunakan setiap hari.'],
        ['name' => 'Oriental', 'slug' => 'oriental', 'description' => 'Racikan amber, vanila, dan rempah yang hangat serta kaya. Menciptakan kesan mewah dan memikat, terutama untuk malam hari.'],
        ['name' => 'Citrus', 'slug' => 'citrus', 'description' => 'Kesegaran lemon dan bergamot yang cerah, ringan, dan membangkitkan semangat. Sempurna untuk hari aktif dan cuaca tropis.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'image' => '/images/categories/'.$category['slug'].'.png',
                    'is_active' => true,
                ]
            );
        }
    }
}
