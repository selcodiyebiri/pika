<?php
namespace Pika;

/**
 * Safe file upload: extension allow-list + real MIME check (finfo) + image decoding check,
 * random server-side file name, and no user-controlled path. Files land in SITE_UPLOAD_PATH,
 * where public/uploads/.htaccess disables script execution.
 *
 *   $saved = Upload::save($_FILES['avatar'], ['dir' => 'avatars', 'max_size' => 1_000_000]);
 *   // ['path' => 'avatars/ab12...png', 'name' => 'ab12...png', 'size' => 1234, 'mime' => 'image/png']
 */
class Upload
{
    /** extension => accepted MIME types (SVG is deliberately absent: it can carry scripts) */
    public const DEFAULT_TYPES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const MAX_PIXELS = 40_000_000;   // decompression-bomb guard
    private const DANGEROUS = ['php', 'phtml', 'phar', 'pht', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps',
        'cgi', 'pl', 'py', 'sh', 'exe', 'bat', 'htaccess', 'htpasswd', 'asp', 'aspx', 'jsp', 'js', 'html', 'htm', 'svg'];

    /**
     * Validate only (nothing is written). Returns [extension, mime].
     * @throws UploadException
     */
    public static function validate(array $file, array $options = []): array
    {
        $types = $options['types'] ?? self::DEFAULT_TYPES;
        $maxSize = $options['max_size'] ?? 2 * 1024 * 1024;

        if (!isset($file['tmp_name'], $file['name'], $file['error'], $file['size']) || is_array($file['error'])) {
            throw new UploadException('Invalid upload.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException(match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                default => 'Upload failed.',
            });
        }
        if (empty($options['allow_non_uploaded']) && !is_uploaded_file($file['tmp_name'])) {
            throw new UploadException('Invalid upload.');
        }

        $size = filesize($file['tmp_name']);
        if ($size === false || $size === 0) throw new UploadException('File is empty.');
        if ($size > $maxSize) throw new UploadException('File is too large.');

        // every dot-separated part of the name is checked, so "shell.php.png" is rejected too
        $parts = explode('.', strtolower(basename((string) $file['name'])));
        if (count($parts) < 2) throw new UploadException('File type is not allowed.');
        $ext = array_pop($parts);
        foreach ([...$parts, $ext] as $part) {
            if (in_array($part, self::DANGEROUS, true)) throw new UploadException('File type is not allowed.');
        }
        if (!isset($types[$ext])) throw new UploadException('File type is not allowed.');

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, $types[$ext], true)) throw new UploadException('File content does not match its type.');

        if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
                throw new UploadException('Invalid image.');
            }
        }

        return [$ext, $mime];
    }

    /** @throws UploadException */
    public static function save(array $file, array $options = []): array
    {
        [$ext, $mime] = self::validate($file, $options);

        $dir = trim((string) ($options['dir'] ?? ''), '/');
        if ($dir !== '' && !preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#i', $dir)) {
            throw new UploadException('Invalid upload directory.');
        }

        $target = rtrim(SITE_UPLOAD_PATH, '/') . ($dir !== '' ? "/$dir" : '');
        if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
            throw new UploadException('Could not create the upload directory.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $moved = empty($options['allow_non_uploaded'])
            ? move_uploaded_file($file['tmp_name'], "$target/$name")
            : copy($file['tmp_name'], "$target/$name");
        if (!$moved) throw new UploadException('Could not store the file.');
        chmod("$target/$name", 0644);

        return [
            'path' => ($dir !== '' ? "$dir/" : '') . $name,
            'name' => $name,
            'size' => filesize("$target/$name"),
            'mime' => $mime,
        ];
    }
}
