<?php
declare(strict_types=1);
namespace Hospital\Services;
use Hospital\Core\HttpException;

final class PhotoUpload
{
    public static function save(?array $file): ?string
    {
        if ($file === null || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new HttpException(422, 'Photo upload failed.');
        if (filesize($file['tmp_name']) > 2 * 1024 * 1024) throw new HttpException(422, 'Photo must be at most 2 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        $size = @getimagesize($file['tmp_name']);
        if ($extension === null || $size === false || $size[0] > 8000 || $size[1] > 8000) throw new HttpException(422, 'Upload a valid JPEG/PNG with dimensions at most 8000 × 8000.');
        $relative = '/uploads/doctors/' . bin2hex(random_bytes(20)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], BASE_PATH . '/public' . $relative)) throw new HttpException(500, 'Photo could not be stored.');
        return $relative;
    }

    public static function remove(?string $path): void
    {
        if ($path && preg_match('~^/uploads/doctors/[a-f0-9]{40}\.(jpg|png)$~', $path)) {
            $file = BASE_PATH . '/public' . $path;
            if (is_file($file)) unlink($file);
        }
    }
}
