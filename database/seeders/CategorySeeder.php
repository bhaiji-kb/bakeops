<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Raw Materials'],
            ['name' => 'Breads'],
            ['name' => 'Cakes'],
            ['name' => 'Pastries'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate($category);
        }
    }
}
