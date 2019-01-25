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
        $testStream = stream_for(fopen('php://temp', 'w+b'));
        $testString = "hallo welt";

        $testStream->write($testString);

        $zip = new ZipStream();
        $zip->add('myfile', $testStream);

        $zipFileName = tempnam(sys_get_temp_dir(), 'ziptest');
        $zipFile = stream_for(fopen($zipFileName, 'w+b'));
        copy_to_stream($zip, $zipFile);

        $this->assertEquals($zipFile->getSize(), $zip->getSize());

        $reader = new \ZipArchive();
        $reader->open($zipFileName);
        $this->assertEquals(1, $reader->numFiles, $zipFileName);
        $this->assertEquals($testString, $reader->getFromName('myfile'), $zipFileName);
    }
}
