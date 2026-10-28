<?php

namespace Database\Seeders\Support;

/**
 * Plausible addresses, postcodes and neighbourhoods around Hanbury Street.
 */
final class EastLondon
{
    private const STREETS = [
        'Brick Lane', 'Columbia Road', 'Cheshire Street', 'Hackney Road', 'Roman Road', 'Mare Street',
        'Vyner Street', 'Cambridge Heath Road', 'Whitechapel Road', 'Commercial Street', 'Old Ford Road',
        'Victoria Park Road', 'Chatsworth Road', 'Lower Clapton Road', 'Bethnal Green Road', 'Wilkes Street',
        'Fournier Street', 'Sclater Street', 'Mile End Road', 'Grove Road', 'Chrisp Street', 'Westferry Road',
        'Kingsland Road', 'Queensbridge Road', 'Dalston Lane', 'Well Street', 'Homerton High Street',
        'Essex Road', 'Packington Street', 'Hoxton Street',
    ];

    /** @var array<string, list<string>> */
    private const AREAS = [
        'E1' => ['Whitechapel', 'Spitalfields', 'Stepney', 'Shadwell'],
        'E2' => ['Bethnal Green', 'Shoreditch', 'Haggerston'],
        'E3' => ['Bow', 'Mile End'],
        'E8' => ['Hackney', 'Dalston', 'London Fields'],
        'E14' => ['Poplar', 'Limehouse', 'Canary Wharf'],
        'E5' => ['Clapton'],
        'E9' => ['Homerton', 'Victoria Park'],
        'N1' => ['Islington', 'Hoxton'],
    ];

    private const LETTERS = 'ABDEFGHJLNPQRSTUWXYZ';

    /**
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return array_keys(self::AREAS);
    }

    public static function postcode(?string $prefix = null): string
    {
        $prefix ??= Dice::pick(self::prefixes());
        $letters = self::LETTERS[mt_rand(0, 19)].self::LETTERS[mt_rand(0, 19)];

        return $prefix.' '.mt_rand(1, 9).$letters;
    }

    public static function area(?string $prefix = null): string
    {
        $areas = self::AREAS[$prefix ?? ''] ?? self::AREAS[Dice::pick(self::prefixes())];

        return Dice::pick($areas);
    }

    public static function person(): string
    {
        return fake('en_GB')->firstName().' '.fake('en_GB')->lastName();
    }

    public static function street(): string
    {
        return mt_rand(1, 160).' '.Dice::pick(self::STREETS);
    }

    /**
     * @return array{line1: string, line2: string|null, city: string, postcode: string}
     */
    public static function address(?string $prefix = null): array
    {
        return [
            'line1' => self::street(),
            'line2' => Dice::chance(0.4) ? 'Flat '.mt_rand(1, 24) : null,
            'city' => 'London',
            'postcode' => self::postcode($prefix),
        ];
    }
}
