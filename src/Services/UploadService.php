<?php
declare(strict_types=1);
namespace McRelish\Services;

use McRelish\Core\{HttpException, Uuid};

/** Saves validated images under public/uploads/<kind>/ and returns the stored file name. */
final class UploadService
{
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    private const MAX_BYTES = 5 * 1024 * 1024;

    public static function saveImage(string $kind): string
    {
        if (!in_array($kind, ['menu', 'promo'], true)) throw new HttpException('Not found', 404);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) throw new HttpException('Choose an image to upload. It may be larger than the server allows.', 422);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) throw new HttpException('That image is too large. Use one up to 5 MB.', 422);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) throw new HttpException('The upload failed. Please try again.', 422);
        if ((int) $file['size'] > self::MAX_BYTES) throw new HttpException('That image is too large. Use one up to 5 MB.', 422);

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) throw new HttpException('Use a JPG, PNG, WebP or GIF image.', 422);

        $dir = dirname(__DIR__, 2) . '/public/uploads/' . $kind;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) throw new HttpException('The upload folder could not be created.', 500);
        $name = Uuid::v4() . '.' . self::TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) throw new HttpException('The image could not be saved.', 500);
        @chmod($dir . '/' . $name, 0644);
        return $name;
    }
}
