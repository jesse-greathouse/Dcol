<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Selector;

class SelectorSeeder extends Seeder
{
    protected array $classes = [
        '\\Dcol\\Selectors\\PdfFileSelector',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->getClasses() as $class) {
            $selector = Selector::where('class', $class)->first();

            if (NULL !== $selector) continue;

            Selector::factory()->create([
                'class' => $class,
            ]);
        }
    }

    /**
     * Get the value of classes
     */ 
    public function getClasses()
    {
        return $this->classes;
    }
}
