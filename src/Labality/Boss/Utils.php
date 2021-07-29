<?php

declare(strict_types=1);

namespace Labality\Boss;

use function getimagesize;
use function imagecolorat;
use function imagecreatefrompng;
use function chr;

class Utils
{

    public static function getImageData(string $path): string{
        $img = imagecreatefrompng($path);
        $bytes = "";
        for ($y = 0; $y < imagesy($img); $y++) {
            for ($x = 0; $x < imagesx($img); $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = (127 - (($rgba >> 24) & 0x7F)) * 2;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($img);
        return $bytes;
    }
}