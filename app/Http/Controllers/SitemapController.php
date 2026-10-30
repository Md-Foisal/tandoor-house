<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    private const CACHE_SECONDS = 3600;

    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        foreach (['staff', 'account', 'checkout', 'basket', 'orders', 't/', 'invite', 'webhooks'] as $path) {
            $lines[] = 'Disallow: /'.$path;
        }

        $lines[] = 'Allow: /';
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function __invoke(): Response
    {
        $xml = Cache::remember('seo.sitemap.v1', self::CACHE_SECONDS, fn () => view('sitemap', [
            'entries' => Seo::sitemapEntries(),
        ])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
