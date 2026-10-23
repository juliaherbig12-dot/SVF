<?php
declare(strict_types=1);
function optimize_image(string $source, string $mime, string $destination, int $maxWidth = 1800, int $quality = 82): bool
{
    if (!extension_loaded('gd')) {
        return copy($source, $destination);
    }

    $info = @getimagesize($source);
    if (!$info) {
        return false;
    }
    [$w, $h] = $info;
    $im = false;

    if ($mime === 'image/jpeg') {
        $im = @imagecreatefromjpeg($source);
    } elseif ($mime === 'image/png') {
        $im = @imagecreatefrompng($source);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $im = @imagecreatefromwebp($source);
    } elseif ($mime === 'image/gif') {
        $im = @imagecreatefromgif($source);
    }
    if (!$im) {
        return false;
    }

    $scale = min(1, $maxWidth / $w);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);

    if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $t = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefill($out, 0, 0, $t);
    }

    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = $mime === 'image/jpeg' ? imagejpeg($out, $destination, $quality) :
        ($mime === 'image/png' ? imagepng($out, $destination, 6) :
            ($mime === 'image/webp' && function_exists('imagewebp') ? imagewebp($out, $destination, $quality) : imagegif($out, $destination)));
    imagedestroy($im);
    imagedestroy($out);
    return $ok;
}
