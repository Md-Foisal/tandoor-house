<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * The installable staff app: its manifest and the page shown when the tablet
 * has no connection. Both are public and carry no user data.
 */
class StaffPwaController extends Controller
{
    // Manifests cannot use CSS variables, so these are the dark "banked coals"
    // values of the paper and ember tokens in docs/design-system.md.
    private const BACKGROUND = '#16110D';

    public function manifest(): JsonResponse
    {
        $name = config('restaurant.name');
        $icon = fn (string $file, int $size, string $purpose) => [
            'src' => "/staff/icons/{$file}",
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => $purpose,
        ];
        $shortcut = fn (string $area, string $url) => [
            'name' => __("staff.areas.{$area}"),
            'url' => $url,
            'icons' => [['src' => '/staff/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png']],
        ];

        return response()->json([
            'id' => '/staff',
            'name' => __('staff.install.app_name', ['name' => $name]),
            'short_name' => $name,
            'description' => __('staff.install.description', ['name' => $name]),
            'lang' => 'en-GB',
            'scope' => '/staff/',
            'start_url' => '/staff',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => self::BACKGROUND,
            'background_color' => self::BACKGROUND,
            'icons' => [
                $icon('icon-192.png', 192, 'any'),
                $icon('icon-512.png', 512, 'any'),
                $icon('icon-maskable-512.png', 512, 'maskable'),
            ],
            'shortcuts' => [
                $shortcut('orders', '/staff/orders'),
                $shortcut('kitchen', '/staff/kitchen'),
                $shortcut('floor', '/staff/floor'),
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'])
            ->setEncodingOptions(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function offline(): Response
    {
        $name = config('restaurant.name');
        $html = str_replace(
            ['{{lang}}', '{{name}}', '{{title}}', '{{body}}', '{{retry}}'],
            [
                'en-GB',
                e($name),
                e(__('staff.install.offline_title')),
                e(__('staff.install.offline_body')),
                e(__('staff.install.offline_retry')),
            ],
            File::get(public_path('staff/offline.html')),
        );

        return response($html)->header('Cache-Control', 'no-cache');
    }
}
