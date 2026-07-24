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

];
