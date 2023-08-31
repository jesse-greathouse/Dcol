<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Document;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }

    /**
     * Overloads the create method of Factory
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @param  \Illuminate\Database\Eloquent\Model|null  $parent
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model|TModel>|\Illuminate\Database\Eloquent\Model|TModel
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        # Ensure that raw_text is base64 encoded upon creation
        if (isset($attributes['raw_text'])) {
            if (base64_encode(base64_decode($attributes['raw_text'], true)) !== $attributes['raw_text']) {
                $attributes['raw_text'] = base64_encode($attributes['raw_text']);
            }
        }

        return parent::create($attributes, $parent);
    }
}
