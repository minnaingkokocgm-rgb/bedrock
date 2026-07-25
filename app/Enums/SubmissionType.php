<?php

namespace App\Enums;

use Illuminate\Http\UploadedFile;

enum SubmissionType: string
{
    case Document = 'document';
    case Image = 'image';
    case Video = 'video';

    /**
     * @return list<string>
     */
    public static function imageExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'ai', 'psd', 'webp', 'svg', 'apng', 'avif', 'bmp', 'ico'];
    }

    /**
     * @return list<string>
     */
    public static function documentExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ods', 'odt', 'pps', 'ppt', 'pptx', 'wpd', 'txt', 'rtf', 'csv'];
    }

    /**
     * @return list<string>
     */
    public static function videoExtensions(): array
    {
        return ['mp4', 'mov', 'avi', 'mkv', 'webm', 'wmv', 'm4v'];
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(): array
    {
        return [
            ...self::imageExtensions(),
            ...self::documentExtensions(),
            ...self::videoExtensions(),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Document => 'Document',
            self::Image => 'Image',
            self::Video => 'Video',
        };
    }

    public static function fromExtension(string $extension): ?self
    {
        $extension = strtolower($extension);

        return match (true) {
            in_array($extension, self::imageExtensions(), true) => self::Image,
            in_array($extension, self::documentExtensions(), true) => self::Document,
            in_array($extension, self::videoExtensions(), true) => self::Video,
            default => null,
        };
    }

    public static function fromMime(string $mime): ?self
    {
        $mime = strtolower($mime);

        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            in_array($mime, [
                'video/x-ms-wmv',
                'audio/x-ms-wmv',
                'video/x-ms-asf',
                'application/vnd.ms-asf',
            ], true) => self::Video,
            in_array($mime, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.oasis.opendocument.text',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.wordperfect',
                'application/wordperfect',
                'application/rtf',
                'text/rtf',
                'text/plain',
                'text/csv',
                'application/csv',
            ], true) => self::Document,
            in_array($mime, [
                'application/postscript',
                'application/illustrator',
                'image/vnd.adobe.photoshop',
                'application/x-photoshop',
                'application/photoshop',
            ], true) => self::Image,
            default => null,
        };
    }

    public static function fromUploadedFile(UploadedFile $file): ?self
    {
        return self::fromExtension($file->getClientOriginalExtension())
            ?? self::fromMime((string) ($file->getMimeType() ?: $file->getClientMimeType()));
    }
}
