<?php

namespace Nemo64\ZipStream\Tests;


use Nemo64\ZipStream\ZipStream;
use PHPUnit\Framework\TestCase;
use function GuzzleHttp\Psr7\copy_to_stream;
use function GuzzleHttp\Psr7\stream_for;

class ZipStreamTest extends TestCase
{
    public function assertZip(array $files)
    {
        $zip = new ZipStream();
        foreach ($files as $name => $stream) {
            $zip->add($name, $stream);
        }

        $zipFileName = tempnam(sys_get_temp_dir(), 'ziptest');
        $zipFile = stream_for(fopen($zipFileName, 'w+b'));
        copy_to_stream($zip, $zipFile);
        $this->assertEquals($zipFile->getSize(), $zip->getSize());

        $reader = new \ZipArchive();
        $reader->open($zipFileName);

        $this->assertEquals(count($files), $reader->numFiles, $zipFileName);

        foreach ($files as $name => $stream) {
            // might get memory problems
            $this->assertEquals($stream->__toString(), $reader->getFromName($name), $zipFileName);
        }
    }

    public function testEmpty()
    {
        $this->assertZip([]);
    }

    public function testBasicFile()
    {
        $this->assertZip([
            'myfile' => stream_for('hallo welt'),
        ]);
    }

    public function testMultipleFiles()
    {
        $this->assertZip([
            'myfile1' => stream_for('hallo welt'),
            'myfile2' => stream_for('welt hallo'),
        ]);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddSameFileTwice()
    {
        $stream = stream_for('hallo welt');
        $this->assertZip([
            'myfile1' => $stream,
            'myfile2' => $stream,
        ]);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testAddTooManyFiles()
    {
        $stream = stream_for('hallo welt');
        $files = array_fill(0, 65536, $stream);
        $this->assertZip($files);
    }
}
