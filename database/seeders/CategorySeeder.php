<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Varian Tea',
            'Varian Yakult',
            'Varian Yogurt',
            'Varian Blend',
            'Varian Coffee',
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['name' => $cat],
                [
                    'slug' => Str::slug($cat),
                    'status' => true,
                ]
            );
        }
    }
}
