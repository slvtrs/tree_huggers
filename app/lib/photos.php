<?php
declare(strict_types=1);

// Photo uploads are validated by sniffing their real MIME type, re-encoded
// through GD as JPEG (which also strips EXIF), resized, and thumbnailed.
// If the original JPEG carried GPS data we hand it back so the tree can be
// placed automatically.

const PHOTO_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

function photo_filenames(string $treeId): array
{
    return ['full' => $treeId . '.jpg', 'thumb' => $treeId . '_t.jpg'];
}

function photo_delete(string $treeId): void
{
    global $config;
    foreach (photo_filenames($treeId) as $name) {
        $p = $config['uploads_dir'] . '/' . $name;
        if (is_file($p)) {
            unlink($p);
        }
    }
}

/**
 * @return array{ok: bool, none?: bool, error?: string, gps?: ?array}
 */
function photo_process(?array $file, string $treeId): array
{
    global $config;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'none' => true];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $tooBig = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        return ['ok' => false, 'error' => $tooBig ? 'That photo is too large.' : 'Upload failed (code ' . $file['error'] . ').'];
    }
    if ($file['size'] > $config['max_upload_bytes']) {
        return ['ok' => false, 'error' => 'Photos must be under ' . round($config['max_upload_bytes'] / 1048576) . ' MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    if (!function_exists('imagecreatefromstring')) {
        return ['ok' => false, 'error' => 'Image processing (GD) is not available on this server.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    if (!isset(PHOTO_MIMES[$mime])) {
        return ['ok' => false, 'error' => 'Please upload a JPEG, PNG, GIF, or WebP image.'];
    }

    $gps = null;
    $orientation = 1;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name'], 'ANY_TAG', true) ?: [];
        $orientation = (int) ($exif['IFD0']['Orientation'] ?? 1);
        $gps = photo_exif_gps($exif['GPS'] ?? []);
    }

    $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
    if (!$src) {
        return ['ok' => false, 'error' => 'That image could not be read.'];
    }
    $src = photo_apply_orientation($src, $orientation);

    $names = photo_filenames($treeId);
    $full  = photo_fit($src, $config['photo_max_px']);
    $thumb = photo_fit($src, $config['thumb_px']);
    $okFull  = imagejpeg($full, $config['uploads_dir'] . '/' . $names['full'], 85);
    $okThumb = imagejpeg($thumb, $config['uploads_dir'] . '/' . $names['thumb'], 80);
    imagedestroy($src);
    imagedestroy($full);
    imagedestroy($thumb);
    if (!$okFull || !$okThumb) {
        photo_delete($treeId);
        return ['ok' => false, 'error' => 'Could not save the photo. Is the uploads folder writable?'];
    }
    return ['ok' => true, 'gps' => $gps];
}

/** Scale an image to fit within $max px on its longest side, flattening transparency onto white. */
function photo_fit($src, int $max)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

function photo_apply_orientation($img, int $orientation)
{
    switch ($orientation) {
        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); return $img;
        case 3: return imagerotate($img, 180, 0) ?: $img;
        case 4: imageflip($img, IMG_FLIP_VERTICAL); return $img;
        case 5: imageflip($img, IMG_FLIP_VERTICAL); return imagerotate($img, -90, 0) ?: $img;
        case 6: return imagerotate($img, -90, 0) ?: $img;
        case 7: imageflip($img, IMG_FLIP_HORIZONTAL); return imagerotate($img, -90, 0) ?: $img;
        case 8: return imagerotate($img, 90, 0) ?: $img;
        default: return $img;
    }
}

/** @return ?array{lat: float, lng: float} */
function photo_exif_gps(array $gps): ?array
{
    if (empty($gps['GPSLatitude']) || empty($gps['GPSLongitude'])) {
        return null;
    }
    $toDeg = function (array $parts): float {
        $vals = [];
        foreach ($parts as $p) {
            if (is_string($p) && str_contains($p, '/')) {
                [$n, $d] = explode('/', $p, 2);
                $vals[] = (float) $d == 0.0 ? 0.0 : (float) $n / (float) $d;
            } else {
                $vals[] = (float) $p;
            }
        }
        return ($vals[0] ?? 0) + ($vals[1] ?? 0) / 60 + ($vals[2] ?? 0) / 3600;
    };
    $lat = $toDeg((array) $gps['GPSLatitude']);
    $lng = $toDeg((array) $gps['GPSLongitude']);
    if (strtoupper((string) ($gps['GPSLatitudeRef'] ?? 'N')) === 'S') $lat = -$lat;
    if (strtoupper((string) ($gps['GPSLongitudeRef'] ?? 'E')) === 'W') $lng = -$lng;
    if ($lat == 0.0 && $lng == 0.0 || abs($lat) > 90 || abs($lng) > 180) {
        return null;
    }
    return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
}
