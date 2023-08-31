<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Site;

class SiteSeeder extends Seeder
{
    protected Array $domains = [
        'supremecourt.gov',
        'judiciary.house.gov'
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->getDomains() as $domain) {
            $site = Site::where('domain_name', $domain)->first();

            if (NULL !== $site) continue;

            Site::factory()->create([
                'domain_name' => $domain,
            ]);
        }
    }

    /**
     * Get the value of domains
     */ 
    public function getDomains()
    {
        return $this->domains;
    }

}
