<?php

namespace Nemo64\ZipStream\Tests;


use Nemo64\ZipStream\ZipResponse;
use Nemo64\ZipStream\ZipStream;
use PHPUnit\Framework\TestCase;

class ZipResponseTest extends TestCase
{
    public function testCreate()
    {
        $stream = new ZipStream();
        $response = ZipResponse::create($stream, "my.zip");
        $this->assertEquals([$stream->getSize()], $response->getHeader('Content-Length'));
        $this->assertEquals(["attachment; filename=my.zip"], $response->getHeader('Content-Disposition'));
    }
}
