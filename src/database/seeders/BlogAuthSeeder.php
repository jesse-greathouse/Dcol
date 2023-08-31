<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\BlogAuth;

class BlogAuthSeeder extends Seeder
{
    /**
     * Associative Array of BlogAuth adapters with their colloquial names.
     *
     * @var array
     */
    protected $blogAuths = [
        'Basic Auth'=> 'Dcol\\WordPress\\Auth\\WordPressBasicAuth',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->getBlogAuths() as $name => $class) {
            $blogAuth = BlogAuth::where('class', $class)->first();

            if (NULL !== $blogAuth) continue;

            BlogAuth::factory()->create([
                'name' => $name,
                'class' => $class,
            ]);
        }
    }

    /**
     * Get the value of urls
     */ 
    public function getBlogAuths(): array
    {
        return $this->blogAuths;
    }
}
