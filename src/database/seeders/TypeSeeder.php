<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Type;

use Dcol\Parsers\PdfParser;

class TypeSeeder extends Seeder
{
    protected array $typeNames = [
        PdfParser::TYPE_NAME,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->getTypeNames() as $typeName) {
            $type = Type::where('type_name', $typeName)->first();

            if (NULL !== $type) continue;

            Type::factory()->create([
                'type_name' => $typeName,
            ]);
        }
    }

    /**
     * Get the value of typeNames
     */ 
    public function getTypeNames()
    {
        return $this->typeNames;
    }
}
