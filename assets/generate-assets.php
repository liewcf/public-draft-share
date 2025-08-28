<?php
// Simple generator to create WP.org-ready placeholder banners and icons.
// Run: php assets/generate-assets.php

declare(strict_types=1);

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}

$outDir = __DIR__;

function colorAlloc($im, $hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat($hex[0], 2));
        $g = hexdec(str_repeat($hex[1], 2));
        $b = hexdec(str_repeat($hex[2], 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return imagecolorallocate($im, $r, $g, $b);
}

function lerp($a, $b, $t) {
    return $a + ($b - $a) * $t;
}

function drawGradient($im, array $startRGB, array $endRGB) {
    $w = imagesx($im);
    $h = imagesy($im);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        $r = (int) lerp($startRGB[0], $endRGB[0], $t);
        $g = (int) lerp($startRGB[1], $endRGB[1], $t);
        $b = (int) lerp($startRGB[2], $endRGB[2], $t);
        $col = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $col);
    }
}

function findFont() : ?string {
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/Library/Fonts/Arial Bold.ttf',
        '/Library/Fonts/Arial.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial.ttf',
    ];
    foreach ($candidates as $f) {
        if (is_readable($f)) return $f;
    }
    return null;
}

function drawTextCenter($im, string $text, int $sizePx, string $colorHex, int $yOffset = 0) : void {
    $w = imagesx($im);
    $h = imagesy($im);
    $col = colorAlloc($im, $colorHex);
    $font = findFont();
    if ($font && function_exists('imagettftext')) {
        // Convert px to pt roughly (96dpi). 1pt ~ 1.333px
        $pt = max(8, (int) round($sizePx / 1.333));
        $bbox = imagettfbbox($pt, 0, $font, $text);
        $textW = abs($bbox[2] - $bbox[0]);
        $textH = abs($bbox[7] - $bbox[1]);
        $x = (int) (($w - $textW) / 2);
        $y = (int) (($h + $textH) / 2) + $yOffset;
        // Shadow
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 80);
        imagettftext($im, $pt, 0, $x+2, $y+2, $shadow, $font, $text);
        imagettftext($im, $pt, 0, $x, $y, $col, $font, $text);
    } else {
        // Fallback to built-in font
        $fontIdx = 5; // largest built-in
        $textW = imagefontwidth($fontIdx) * strlen($text);
        $textH = imagefontheight($fontIdx);
        $x = (int) (($w - $textW) / 2);
        $y = (int) (($h - $textH) / 2) + $yOffset;
        imagestring($im, $fontIdx, $x, $y, $text, $col);
    }
}

function createImage(int $w, int $h) {
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    imagealphablending($im, true);
    return $im;
}

function savePng($im, string $path) : void {
    imagepng($im, $path, 6);
    imagedestroy($im);
}

function makeBanner(string $path, int $w, int $h) : void {
    $im = createImage($w, $h);
    // Gradient: slate-900 -> indigo-600
    drawGradient($im, [17,24,39], [79,70,229]);
    // Accent diagonal overlay
    $overlay = imagecolorallocatealpha($im, 255, 255, 255, 100);
    imagefilledpolygon($im, [
        (int)($w*0.55), 0,
        $w, 0,
        $w, $h,
        (int)($w*0.8), $h,
    ], $overlay);
    // Title + subtitle
    drawTextCenter($im, 'Public Draft Share', (int)($h*0.36), '#ffffff', (int)(-$h*0.08));
    drawTextCenter($im, 'Secure share links for drafts', (int)($h*0.18), '#dbeafe', (int)($h*0.18));
    savePng($im, $path);
}

function makeIcon(string $path, int $w, int $h) : void {
    $im = createImage($w, $h);
    drawGradient($im, [30,41,59], [37,99,235]);
    // Rounded rectangle mask (simple simulated by border)
    $border = imagecolorallocatealpha($im, 255,255,255,60);
    imagerectangle($im, 2, 2, $w-3, $h-3, $border);
    // Letters
    drawTextCenter($im, 'PDS', (int)($h*0.48), '#ffffff');
    savePng($im, $path);
}

// Generate all assets
$assets = [
    ['banner-772x250.png', 772, 250, 'banner'],
    ['banner-1544x500.png', 1544, 500, 'banner'],
    ['icon-128x128.png', 128, 128, 'icon'],
    ['icon-256x256.png', 256, 256, 'icon'],
];

foreach ($assets as [$name, $w, $h, $type]) {
    $path = $outDir . DIRECTORY_SEPARATOR . $name;
    if ($type === 'banner') {
        makeBanner($path, $w, $h);
    } else {
        makeIcon($path, $w, $h);
    }
    echo "Generated: {$name}\n";
}

echo "Done. Upload these to the WordPress.org SVN 'assets/' directory.\n";
