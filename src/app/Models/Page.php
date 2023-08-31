<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Exception\UrlNotCompatibleException;

class Page extends Model
{
    use HasFactory;

    /**
     * Get the site that owns the page.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the selectors for the page.
     */
    public function selectors(): BelongsToMany
    {
        return $this->belongsToMany(Selector::class);
    }

    /**
     * Overloads the save method.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        # A check to make sure Site is set up before it saves.
        $this->verifySite();

        $save = parent::save($options);

        # A check to make sure the page has at least one Selector before it saves.
        if ($save) {
            $this->verifySelector($this);
        }

        return $save;
    }


    /**
     * Provides a Site for a Page where a saved page does not currently have a saved domain
     * 
     * @return void
     */
    protected function verifySite(): void 
    {

        # If the site domain is not currently in the database then add it now.
        $parts = parse_url($this->url);

        if (!is_array($parts) || !isset($parts['host']) || NULL === $parts['host']) {
            throw new UrlNotCompatibleException('Url was not compatible or host is missing from url');
        }

        $domain = $parts['host'];
    
        # Look up domain in Site
        $site = Site::where('domain_name', $domain)->first();

        # If Site for this domain does not exist then add it.
        if (NULL === $site) {
            $site = Site::factory()->create([
                'domain_name'       => $domain,
                'crawl_count'       => 0,
                'last_crawled_at'   => new \DateTime(),
            ]);
        }

        $this->site_id = $site->id;
    }

    /**
     * Provides a Selector to the collection of selectors because a saved page should always have a Selector
     * 
     * @return void
     */
    protected function verifySelector(): void 
    {
        if ( 1 > count($this->selectors)) {
            $selector = Selector::all()->first();
            $this->selectors()->attach($selector->id);
        }
    }

}
