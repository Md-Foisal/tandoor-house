<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacy(): Response
    {
        $company = (array) config('restaurant.company');
        $address = (array) config('restaurant.contact.address');
        $ico = filled($company['ico_number'] ?? null) ? (string) $company['ico_number'] : null;

        $replace = [
            ':name' => (string) config('restaurant.name'),
            ':legal' => (string) ($company['legal_name'] ?? config('restaurant.name')),
            ':number' => (string) ($company['company_number'] ?? ''),
            ':email' => (string) config('restaurant.contact.email'),
            ':address' => implode(', ', array_filter([
                $address['line1'] ?? null,
                $address['city'] ?? null,
                $address['postcode'] ?? null,
            ])),
            ':ico' => $ico ?? (string) __('legal.privacy.ico_pending'),
        ];

        $sections = collect((array) __('legal.privacy.sections'))
            ->map(fn (array $section, string $id) => [
                'id' => $id,
                'title' => (string) $section['title'],
                'paragraphs' => array_map(fn (string $line) => strtr($line, $replace), (array) ($section['paragraphs'] ?? [])),
                'items' => array_map(fn (string $line) => strtr($line, $replace), (array) ($section['items'] ?? [])),
            ])
            ->values()
            ->all();

        return Inertia::render('legal/privacy', [
            'updated' => $this->updated(),
            'sections' => $sections,
            'email' => $replace[':email'],
            'icoRegistered' => $ico !== null,
        ]);
    }

    public function cookies(): Response
    {
        $items = collect((array) config('cookies.items'))
            ->filter(fn (array $item) => ! isset($item['requires']) || filled(config($item['requires'])))
            ->map(fn (array $item) => [
                'id' => (string) $item['id'],
                'name' => (string) ($item['name'] ?? config('session.cookie')),
                'category' => (string) $item['category'],
                'storage' => (string) $item['storage'],
                'duration' => $this->duration($item['duration']),
                'purpose' => (string) __("legal.cookies.items.{$item['id']}.purpose"),
            ])
            ->values()
            ->all();

        return Inertia::render('legal/cookies', [
            'updated' => $this->updated(),
            'items' => $items,
        ]);
    }

    private function updated(): string
    {
        return now()->parse((string) config('cookies.policy_updated'))->translatedFormat('j F Y');
    }

    /**
     * @param  string|array{minutes?: int, days?: int}  $duration
     */
    private function duration(string|array $duration): string
    {
        if ($duration === 'session_lifetime') {
            $duration = ['minutes' => (int) config('session.lifetime')];
        }

        if (is_string($duration)) {
            return (string) __("legal.cookies.durations.{$duration}");
        }

        $days = (int) ($duration['days'] ?? 0);

        if ($days >= 365 && $days % 365 === 0) {
            return trans_choice('legal.cookies.durations.years', intdiv($days, 365));
        }

        if ($days > 0) {
            return trans_choice('legal.cookies.durations.days', $days);
        }

        $minutes = (int) ($duration['minutes'] ?? 0);

        return $minutes >= 60 && $minutes % 60 === 0
            ? trans_choice('legal.cookies.durations.hours', intdiv($minutes, 60))
            : trans_choice('legal.cookies.durations.minutes', $minutes);
    }
}
