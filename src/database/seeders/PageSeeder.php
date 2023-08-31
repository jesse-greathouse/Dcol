<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Page;

class PageSeeder extends Seeder
{

    protected array $urls = [
        'https://judiciary.house.gov/documents/reports',
        'https://judiciary.house.gov/documents/letters',
        //'https://www.supremecourt.gov/opinions/in-chambers.aspx',
        //'https://www.supremecourt.gov/opinions/USReports.aspx',
        'https://www.supremecourt.gov/opinions/slipopinion/22',
        //'https://www.supremecourt.gov/opinions/slipopinion/21',
        //'https://www.supremecourt.gov/opinions/slipopinion/20',
        //'https://www.supremecourt.gov/opinions/slipopinion/19',
        //'https://www.supremecourt.gov/opinions/slipopinion/18',
        //'https://www.supremecourt.gov/opinions/slipopinion/17',
        //'https://www.supremecourt.gov/opinions/slipopinion/16',
        'https://www.supremecourt.gov/opinions/relatingtoorders/22',
        //'https://www.supremecourt.gov/opinions/relatingtoorders/21',
        //'https://www.supremecourt.gov/opinions/relatingtoorders/20',
        //'https://www.supremecourt.gov/opinions/relatingtoorders/19',
        //'https://www.supremecourt.gov/opinions/relatingtoorders/18',
        //'https://www.supremecourt.gov/opinions/relatingtoorders/17',
        //'https://www.supremecourt.gov/opinions/relatingtoorders/16',
        'https://www.supremecourt.gov/opinions/cited_urls/22',
        //'https://www.supremecourt.gov/opinions/cited_urls/21',
        //'https://www.supremecourt.gov/opinions/cited_urls/20',
        //'https://www.supremecourt.gov/opinions/cited_urls/19',
        //'https://www.supremecourt.gov/opinions/cited_urls/18',
        //'https://www.supremecourt.gov/opinions/cited_urls/17',
        //'https://www.supremecourt.gov/opinions/cited_urls/16',

    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->getUrls() as $url) {
            $page = Page::where('url', $url)->first();

            if (NULL !== $page) continue;

            Page::factory()->create([
                'url' => $url,
            ]);
        }
    }

    /**
     * Get the value of urls
     */ 
    public function getUrls(): array
    {
        return $this->urls;
    }
}
