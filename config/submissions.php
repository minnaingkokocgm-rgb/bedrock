<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Submissions disk
    |--------------------------------------------------------------------------
    |
    | local = app private storage, s3 = AWS S3 bucket from filesystems.disks.s3.
    | When using s3, Bedrock Converse can read files via s3:// URIs.
    |
    */

    'disk' => env('SUBMISSIONS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Max upload sizes (kilobytes)
    |--------------------------------------------------------------------------
    |
    | App validation ceilings. PHP post_max_size / upload_max_filesize and the
    | web server body limit must be at least this large or PostTooLargeException
    | will fire before Laravel validation runs.
    |
    */

    'max_kilobytes' => [
        'document' => (int) env('SUBMISSIONS_MAX_DOCUMENT_KB', 20 * 1024),
        'image' => (int) env('SUBMISSIONS_MAX_IMAGE_KB', 10 * 1024),
        // ~1GB default so ~800MB test videos clear app validation (PHP/server must match).
        'video' => (int) env('SUBMISSIONS_MAX_VIDEO_KB', 1024 * 1024),
    ],

];
