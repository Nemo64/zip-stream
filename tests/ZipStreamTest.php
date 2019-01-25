<?php

namespace Nemo64\ZipStream\Tests;


use function GuzzleHttp\Psr7\copy_to_stream;
use function GuzzleHttp\Psr7\stream_for;
use Nemo64\ZipStream\ZipStream;
use PHPUnit\Framework\TestCase;

class ZipStreamTest extends TestCase
{
    public function testBasicFile()
    {
        $testStream = stream_for("hallo welt");

        $zip = new ZipStream();
        $zip->add('myfile', $testStream);

        $zipFileName = tempnam(sys_get_temp_dir(), 'ziptest');
        $zipFile = stream_for(fopen($zipFileName, 'w+b'));
        copy_to_stream($zip, $zipFile);

        $this->assertEquals($zipFile->getSize(), $zip->getSize());

        $reader = new \ZipArchive();
        $reader->open($zipFileName);
        $this->assertEquals(1, $reader->numFiles, $zipFileName);
        $this->assertEquals($testStream->__toString(), $reader->getFromName('myfile'), $zipFileName);
    }
}
