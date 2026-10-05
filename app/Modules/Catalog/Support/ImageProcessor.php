<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Files\Exceptions\FileRejected;
use Illuminate\Http\UploadedFile;

/**
 * Chaîne de validation des images de service : taille, extension, type RÉEL (finfo), image décodable, dimensions bornées, puis
 * RÉENCODAGE complet en WebP (grande taille et vignette 3:2). L'original n'est jamais conservé ni servi : métadonnées, scripts ou
 * données ajoutées au fichier disparaissent au réencodage. Nécessite l'extension GD (avec WebP) ; sans elle, le dépôt est désactivé.
 */
final class ImageProcessor
{
    private const ALLOWED = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /** @return array{large: string, card: string, width: int, height: int, mime: string, sha256: string} */
    public function process(UploadedFile $file): array
    {
        if (! self::available()) {
            throw new FileRejected('Le dépôt d’images est désactivé sur cette installation (extension GD absente).');
        }
        $c = config('freeci.catalog');
        if (! $file->isValid()) {
            throw new FileRejected('Le téléversement a échoué : réessayez.');
        }
        if ($file->getSize() === 0 || $file->getSize() > $c['image_max_mb'] * 1048576) {
            throw new FileRejected('Image trop volumineuse ou vide (maximum '.$c['image_max_mb'].' Mo).');
        }
        $name = strtolower((string) $file->getClientOriginalName());
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (! isset(self::ALLOWED[$ext])) {
            throw new FileRejected('Format non autorisé. Formats acceptés : JPG, PNG, WebP.');
        }
        $path = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (! in_array($mime, self::ALLOWED[$ext], true)) {
            throw new FileRejected('Le contenu du fichier ne correspond pas à son format.');
        }
        $info = @getimagesize($path);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new FileRejected('Image illisible.');
        }
        [$w, $h] = $info;
        if ($w < $c['image_min_width'] || $w * $h > $c['image_max_pixels']) {
            throw new FileRejected('Dimensions non acceptées : largeur minimale '.$c['image_min_width'].' px, au plus '.number_format($c['image_max_pixels'] / 1000000, 0).' millions de pixels.');
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            throw new FileRejected('Image illisible.');
        }
        try {
            $large = $this->encode($this->fit($src, $w, $h, 1600, 1600));
            $card = $this->encode($this->cover($src, $w, $h, 640, 427));
        } finally {
            imagedestroy($src);
        }
        $size = getimagesizefromstring($large);

        return ['large' => $large, 'card' => $card, 'width' => $size[0], 'height' => $size[1], 'mime' => 'image/webp', 'sha256' => hash('sha256', $large)];
    }

    private function canvas(int $w, int $h): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));

        return $im;
    }

    private function fit(\GdImage $src, int $w, int $h, int $maxW, int $maxH): \GdImage
    {
        $r = min(1, $maxW / $w, $maxH / $h);
        $nw = max(1, (int) round($w * $r));
        $nh = max(1, (int) round($h * $r));
        $dst = $this->canvas($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private function cover(\GdImage $src, int $w, int $h, int $tw, int $th): \GdImage
    {
        $scale = max($tw / $w, $th / $h);
        $cw = (int) round($tw / $scale);
        $ch = (int) round($th / $scale);
        $dst = $this->canvas($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, (int) round(($w - $cw) / 2), (int) round(($h - $ch) / 2), $tw, $th, $cw, $ch);

        return $dst;
    }

    private function encode(\GdImage $im): string
    {
        ob_start();
        imagewebp($im, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }
}
