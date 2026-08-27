<?php

namespace App\Support;

/**
 * The real upload ceilings, read from PHP's own configuration.
 *
 * PHP enforces `post_max_size` and `upload_max_filesize` *before* the request
 * reaches Laravel, so a validation rule more generous than the ini settings
 * can never be satisfied — the request is rejected at startup with a 413 and
 * no useful message. Deriving the rules and the on-screen copy from the same
 * source keeps the advertised capacity and the real capacity from drifting
 * apart.
 */
class UploadLimits
{
    /**
     * Largest single file PHP will accept, in kilobytes.
     *
     * Capped by post_max_size as well: a lone file can never exceed the total
     * request ceiling, so when post_max_size is the smaller of the two it is
     * the real per-file limit.
     */
    public static function perFileKb(): int
    {
        $perFile = self::bytes(ini_get('upload_max_filesize')) / 1024;
        $perRequest = self::bytes(ini_get('post_max_size')) / 1024;

        return (int) max(1, min($perFile, $perRequest));
    }

    /** Largest whole request PHP will accept, in kilobytes. */
    public static function perRequestKb(): int
    {
        return (int) max(1, self::bytes(ini_get('post_max_size')) / 1024);
    }

    /** How many files PHP will accept in one request. */
    public static function maxFiles(): int
    {
        $configured = (int) ini_get('max_file_uploads');

        return $configured > 0 ? $configured : 20;
    }

    /**
     * How many files to advertise for one batch.
     *
     * This is max_file_uploads, not post_max_size divided by the per-file
     * ceiling: dividing would assume every file is the maximum size and would
     * badly under-promise (20 small PDFs fit comfortably inside a request that
     * could not hold 20 maximum-size ones). The total-size limit is enforced
     * separately — client-side before submitting, and by PHP regardless.
     */
    public static function effectiveMaxFiles(): int
    {
        return self::maxFiles();
    }

    public static function perFileLabel(): string
    {
        return self::humanKb(self::perFileKb());
    }

    public static function perRequestLabel(): string
    {
        return self::humanKb(self::perRequestKb());
    }

    private static function humanKb(int $kb): string
    {
        return $kb >= 1024
            ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.').' MB'
            : $kb.' KB';
    }

    /** Parse a PHP shorthand size ("8M", "512K", "1G") into bytes. */
    private static function bytes(string|false $value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        $number = (float) $value;
        $suffix = strtolower(substr($value, -1));

        return (int) match ($suffix) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
