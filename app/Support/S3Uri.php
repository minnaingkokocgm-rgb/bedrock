<?php

namespace App\Support;

use InvalidArgumentException;

final class S3Uri
{
    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
    ) {}

    public static function parse(string $uri): self
    {
        $uri = trim($uri);

        if (! preg_match('#^s3://([^/]+)/(.+)$#i', $uri, $matches)) {
            throw new InvalidArgumentException('S3 URI must look like s3://bucket/key.');
        }

        $bucket = $matches[1];
        $key = ltrim($matches[2], '/');

        if ($bucket === '' || $key === '') {
            throw new InvalidArgumentException('S3 URI must include a bucket and object key.');
        }

        return new self($bucket, $key);
    }

    public function filename(): string
    {
        return basename($this->key) ?: $this->key;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->filename(), PATHINFO_EXTENSION));
    }

    public function toString(): string
    {
        return 's3://'.$this->bucket.'/'.$this->key;
    }
}
