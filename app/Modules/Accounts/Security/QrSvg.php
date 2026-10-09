<?php

namespace App\Modules\Accounts\Security;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Code QR en SVG, calculé sur le serveur : le secret ne quitte jamais l'application (aucun service tiers de génération). */
final class QrSvg
{
    public static function for(string $text, int $size = 200): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd)))->writeString($text);

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }
}
