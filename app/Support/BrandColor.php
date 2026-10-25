<?php

namespace App\Support;

use App\Models\CompanySetting;

/**
 * A company's brand colour (company setting `brand_color`), used for the PDFs' accents.
 * Without one, the documents keep InvoiceShelf's own purples.
 */
final class BrandColor
{
    public const DEFAULT_PRIMARY = '#5851DB';

    public const DEFAULT_ACCENT = '#7675FF';

    /** WCAG AA for UI components and bold text: buttons put white text on the brand colour. */
    public const MINIMUM_CONTRAST_WITH_WHITE = 3.0;

    public function __construct(
        public readonly string $primary,
        public readonly string $accent,
    ) {}

    public static function forCompany($companyId): self
    {
        $color = CompanySetting::getSetting('brand_color', $companyId);

        return self::isValid($color)
            ? new self($color, $color)
            : new self(self::DEFAULT_PRIMARY, self::DEFAULT_ACCENT);
    }

    public static function isValid(mixed $color): bool
    {
        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;
    }

    /**
     * The WCAG contrast ratio between white and the colour, from 1 to 21.
     */
    public static function contrastWithWhite(string $color): float
    {
        [$red, $green, $blue] = array_map(
            fn (string $channel) => self::linear(hexdec($channel) / 255),
            str_split(ltrim($color, '#'), 2)
        );

        $luminance = 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;

        return 1.05 / ($luminance + 0.05);
    }

    private static function linear(float $channel): float
    {
        return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
