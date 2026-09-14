<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Menu;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Varian Tea' => [
                'prefix' => 'TEA',
                'items' => [
                    ['name' => 'Jasmine Tea', 'price' => 4000],
                    ['name' => 'Vanilla Tea', 'price' => 4000],
                    ['name' => 'Green Tea Original', 'price' => 5000],
                    ['name' => 'Strawberry Tea', 'price' => 7000],
                    ['name' => 'Pasion Tea', 'price' => 7000],
                    ['name' => 'Leci Tea', 'price' => 7000],
                    ['name' => 'Apel Tea', 'price' => 7000],
                    ['name' => 'Melon Tea', 'price' => 7000],
                    ['name' => 'Lemon Tea', 'price' => 7000],
                    ['name' => 'Grape Tea', 'price' => 7000],
                    ['name' => 'Mango Tea', 'price' => 7000],
                    ['name' => 'The Tarik Aceh', 'price' => 9000],
                    ['name' => 'Thai Tea Grentea', 'price' => 11000],
                ]
            ],
            'Varian Yakult' => [
                'prefix' => 'YKT',
                'items' => [
                    ['name' => 'Yakult Melon', 'price' => 10000],
                    ['name' => 'Yakult Orange', 'price' => 10000],
                    ['name' => 'Yakult Leci', 'price' => 10000],
                    ['name' => 'Yakult Mangga', 'price' => 10000],
                    ['name' => 'Yakult Strawberry', 'price' => 10000],
                    ['name' => 'Yakult Lemon', 'price' => 10000],
                ]
            ],
            'Varian Yogurt' => [
                'prefix' => 'YGT',
                'items' => [
                    ['name' => 'Yogurt Melon', 'price' => 9000],
                    ['name' => 'Yogurt Strawberry', 'price' => 9000],
                    ['name' => 'Yogurt Lecy', 'price' => 9000],
                    ['name' => 'Yogurt Lemon', 'price' => 9000],
                ]
            ],
            'Varian Blend' => [
                'prefix' => 'BLN',
                'items' => [
                    ['name' => 'Silverquen', 'price' => 11000],
                    ['name' => 'Redvelvet', 'price' => 11000],
                    ['name' => 'Cookies & Cream', 'price' => 11000],
                    ['name' => 'Ketan Hitam', 'price' => 11000],
                    ['name' => 'Choco Avocado', 'price' => 11000],
                    ['name' => 'Taro', 'price' => 11000],
                    ['name' => 'Bubblegum', 'price' => 11000],
                    ['name' => 'Tiramisu', 'price' => 11000],
                    ['name' => 'Bengbeng', 'price' => 11000],
                    ['name' => 'Matcha', 'price' => 13000],
                ]
            ],
            'Varian Coffee' => [
                'prefix' => 'COF',
                'items' => [
                    ['name' => 'Brown Sugar Cofee', 'price' => 11000],
                    ['name' => 'Vietnam Cofe', 'price' => 11000],
                    ['name' => 'Creamy Machiato', 'price' => 13000],
                    ['name' => 'Americano Coffe', 'price' => 13000],
                    ['name' => 'Avocado Cofee', 'price' => 16000],
                    ['name' => 'Vanilla Latte', 'price' => 16000],
                    ['name' => 'Mocca Cofee', 'price' => 16000],
                    ['name' => 'Hazelnut Cofee', 'price' => 16000],
                ]
            ],
        ];

        foreach ($categories as $catName => $catData) {
            $category = Category::where('name', $catName)->first();
            if ($category) {
                $i = 1;
                foreach ($catData['items'] as $item) {
                    $code = $catData['prefix'] . '-' . str_pad($i++, 3, '0', STR_PAD_LEFT);
                    Menu::updateOrCreate(
                        ['name' => $item['name']],
                        [
                            'category_id' => $category->id,
                            'code' => $code,
                            'price' => $item['price'],
                            'profit_percentage' => null,
                            'use_global_profit' => true,
                            'status' => true,
                        ]
                    );
                }
            }
        }
    }
}
