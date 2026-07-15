<?php

namespace App\Support;

/**
 * Shared brand assets for payment / order slip PDF HTML.
 * Embeds logo + Torus (brand) font as data URIs so DomPDF / browsers
 * do not depend on relative public paths inside generated documents.
 */
class PaymentSlipBrand
{
    public const GREEN = '#308B49';

    public static function logoPath(): string
    {
        return public_path('images/brand/icon.png');
    }

    public static function torusFontPath(): string
    {
        return resource_path('fonts/Torus-SemiBold.ttf');
    }

    public static function myanmarFontPath(): string
    {
        $ttf = resource_path('fonts/NotoSansMyanmar-Regular.ttf');
        if (is_file($ttf)) {
            return $ttf;
        }

        return resource_path('fonts/NotoSansMyanmar-Regular.woff2');
    }

    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    public static function fontFacesCss(): string
    {
        $faces = [];

        $torus = self::torusFontPath();
        if (is_file($torus)) {
            $faces[] = '@font-face{font-family:"Torus-SemiBold";src:url("data:font/ttf;base64,'
                .base64_encode((string) file_get_contents($torus))
                .'") format("truetype");font-weight:600;font-style:normal;}';
        }

        $myanmar = self::myanmarFontPath();
        if (is_file($myanmar)) {
            $isTtf = str_ends_with(strtolower($myanmar), '.ttf');
            $mime = $isTtf ? 'font/ttf' : 'font/woff2';
            $format = $isTtf ? 'truetype' : 'woff2';
            $faces[] = '@font-face{font-family:"Noto Sans Myanmar";src:url("data:'.$mime.';base64,'
                .base64_encode((string) file_get_contents($myanmar))
                .'") format("'.$format.'");font-weight:400;font-style:normal;}';
        }

        return implode("\n", $faces);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function viewData(array $extra = []): array
    {
        return array_merge([
            'accentColor' => self::GREEN,
            'logoDataUri' => self::logoDataUri() ?? asset('images/brand/icon.png'),
            'fontFacesCss' => self::fontFacesCss(),
        ], $extra);
    }
}
