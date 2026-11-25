<?php
declare(strict_types=1);

// Re-encode uploads so public images and PDF tickets use the same JPEG.
function normalizeCover(string $path): string {
    $source = @imagecreatefromstring(file_get_contents($path));
    if (!$source) throw new DomainException('The image could not be read. Choose another photo.');
    $scale = min(1, 1600 / max(imagesx($source), imagesy($source)));
    $width = max(1, (int)round(imagesx($source) * $scale));
    $height = max(1, (int)round(imagesy($source) * $scale));
    $canvas = imagecreatetruecolor($width, $height);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
    if (function_exists('exif_read_data') && exif_imagetype($path) === IMAGETYPE_JPEG) {
        $metadata = @exif_read_data($path, 'IFD0');
        $orientation = (int)($metadata['Orientation'] ?? 1);
        if (in_array($orientation, [2,5,7], true)) imageflip($canvas, IMG_FLIP_HORIZONTAL);
        if ($orientation === 4) imageflip($canvas, IMG_FLIP_VERTICAL);
        $angle = match ($orientation) { 3 => 180, 5,8 => 90, 6,7 => -90, default => 0 };
        if ($angle) $canvas = imagerotate($canvas, $angle, 0);
    }
    ob_start();
    try {
        if (!imagejpeg($canvas, null, 86)) throw new RuntimeException('Image encoding failed.');
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

function uploadedCover(): ?string {
    $file = $_FILES['cover'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > 8*1024*1024)
        throw new DomainException('Choose an image no larger than 8 MB.');
    $path = $file['tmp_name'];
    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true))
        throw new DomainException('Choose a JPEG, PNG or WebP image.');
    if ($info[0]*$info[1] > 24000000) throw new DomainException('Choose an image of 24 megapixels or less.');
    if (extension_loaded('gd')) return normalizeCover($path);

    // XAMPP ships GD as an optional module. Load it for the image worker only.
    $ini = php_ini_loaded_file();
    $cli = PHP_OS_FAMILY === 'Windows' && $ini ? dirname($ini).'/php.exe' : 'php';
    $cmd = [$cli, '-d', 'extension=gd', '-d', 'display_errors=stderr', __DIR__.'/../scripts/encode-cover.php', $path];
    $process = proc_open($cmd, [1=>['pipe','w'], 2=>['pipe','w']], $pipes, null, null, ['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('Unable to start the image encoder.');
    $jpeg = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $status = proc_close($process);
    if ($status === 2) throw new DomainException('The image could not be read. Choose another photo.');
    if ($status !== 0 || !str_starts_with($jpeg, "\xFF\xD8")) {
        error_log('Cover encoder: '.$error);
        throw new RuntimeException('Image encoder failed.');
    }
    return $jpeg;
}