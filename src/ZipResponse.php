<?php

namespace Nemo64\ZipStream;


use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class ZipResponse
{
    protected final function __construct()
    {
        throw new \LogicException(__CLASS__ . " is a utility class and can't be instantiated");
    }

    public static function create(ZipStream $stream, string $filename): ResponseInterface
    {
        $headers = [];

        $filenameFallback = preg_replace("/[^a-zA-Z0-9\\._-]/", '_', $filename);
        $headers['Content-Disposition'] = $filenameFallback !== $filename
            ? "attachment; filename=$filenameFallback; filename*=UTF-8''" . urlencode($filename)
            : "attachment; filename=$filenameFallback";

        $size = $stream->getSize();
        if (is_int($size) && $size <= PHP_INT_MAX && $size > 0) {
            $headers['Content-Length'] = $size;
        } else {
            $errorMsg = "$filename exceeds PHP_INT_MAX and is therefor not precise. Content-Length is omitted.";
            trigger_error($errorMsg, E_USER_WARNING);
        }

        return new Response(200, $headers, $stream);
    }
}
