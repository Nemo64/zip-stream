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
        $filenameFallback = preg_replace("/[^a-zA-Z0-9\\._-]/", '_', $filename);

        $headers = [
            'Content-Disposition' => $filenameFallback !== $filename
                ? "attachment; filename=$filenameFallback; filename*=UTF-8''" . urlencode($filename)
                : "attachment; filename=$filenameFallback",
            'Content-Length' => $stream->getSize(),
        ];

        return new Response(200, $headers, $stream);
    }
}
