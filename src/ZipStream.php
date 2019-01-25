<?php

namespace Nemo64\ZipStream;


use GuzzleHttp\Psr7\AppendStream;
use function GuzzleHttp\Psr7\stream_for;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class ZipStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var StreamInterface[] */
    private $files = [];

    /** @var string[] */
    private $crc32 = [];

    /** @var bool */
    private $locked = false;

    public function __construct()
    {
    }

    public function add(string $name, StreamInterface $stream)
    {
        $name = ltrim($name, '/');
        if (isset($this->files[$name])) {
            throw new \RuntimeException("File with name '$name' is already added.", 1545241587);
        }

        if (strlen($name) > 65535) {
            throw new \RuntimeException("Filename too long.", 1545244717);
        }

        if (!$stream->isReadable()) {
            throw new \RuntimeException("'$name' is not readable.", 1545241909);
        }

        if (!$stream->isSeekable()) {
            throw new \RuntimeException("'$name' is not seekable.", 1545241897);
        }

        $size = $stream->getSize();
        if (!is_int($size)) {
            throw new \RuntimeException("Could not determine the size of '$name'.", 1545241697);
        }

        if ($size > 4294967295) {
            throw new \RuntimeException("File above 4GB are not implemented.", 1545244929);
        }

        if (count($this->files) === 65535) {
            throw new \RuntimeException("A zip file can only contain 65535 files.");
        }

        $this->files[$name] = $stream;
    }

    private function getCrc32(string $name): int
    {
        if (!isset($this->crc32[$name])) {
            $this->crc32[$name] = hexdec(\GuzzleHttp\Psr7\hash($this->files[$name], 'crc32b'));
        }

        return $this->crc32[$name];
    }

    protected function createStream()
    {
        $this->locked = true;

        $stream = new AppendStream();
        $cdsStreams = [];
        $cdsPosition = 0;
        $cdsSize = 0;

        foreach ($this->files as $fileName => $fileStream) {
            $fileStream->rewind();

            $headerStream = $this->getHeaderStream($fileName);
            $stream->addStream($headerStream);
            $stream->addStream($fileStream);

            $cdsStreams[] = $this->getCdsStream($fileName, $cdsPosition);
            $cdsPosition += $headerStream->getSize() + $fileStream->getSize();
        }

        foreach ($cdsStreams as $cdsStream) {
            $stream->addStream($cdsStream);
            $cdsSize += $cdsStream->getSize();
        }

        $stream->addStream(stream_for(
            pack('V', 0x06054b50) // end of central file header signature
            . pack('v', 0x00) // this disk number
            . pack('v', 0x00) // number of disk with cdr
            . pack('v', count($this->files)) // number of entries (on this disk)
            . pack('v', count($this->files)) // number of entries
            . pack('V', $cdsSize) // cds size
            . pack('V', $cdsPosition) // cds position
            . pack('v', 0x00) // zip file comment length
        ));

        return $stream;
    }

    private function getHeaderStream(string $name)
    {
        $headerSize = 30 + strlen($name);
        $callback = function () use ($name, $headerSize) {
            $str = pack('V', 0x04034b50) // local file header signature
                . pack('v', 0x000A) // version needed to extract
                . pack('v', 0x08) // general purpose bit flag (defines UTF-8 filename here)
                . pack('v', 0x00) // compression method... in this case none
                . pack('V', 0) // TODO dos timestamp
                . pack('V', $this->getCrc32($name)) // crc32 of data
                . pack('V', $this->files[$name]->getSize()) // compressed size
                . pack('V', $this->files[$name]->getSize()) // uncompressed size ~ obviously the same if not compressed
                . pack('v', strlen($name))
                . pack('v', 0x00) // extra data length
                . $name;

            $actualHeaderSize = strlen($str);
            if ($actualHeaderSize !== $headerSize) {
                throw new \LogicException("Header size miscalculated. Expected $headerSize, got $actualHeaderSize for $name.");
            }

            return $str;
        };

        return new LazyCallbackStream($callback, $headerSize);
    }

    private function getCdsStream(string $name, int $offset)
    {
        $headerSize = 46 + strlen($name);
        $callback = function () use ($name, $offset, $headerSize) {
            $str = pack('V', 0x02014b50) // central file header signature
                . pack('v', 0x003F) // Ver 6.3, OS_FAT
                . pack('v', 0x000A) // version needed to extract
                . pack('v', 0x08) // general purpose bit flag (defines UTF-8 filename here)
                . pack('v', 0x00) // compression method... in this case none
                . pack('V', 0) // TODO dos timestamp
                . pack('V', $this->getCrc32($name)) // crc32 of data
                . pack('V', $this->files[$name]->getSize()) // compressed size
                . pack('V', $this->files[$name]->getSize()) // uncompressed size ~ obviously the same if not compressed
                . pack('v', strlen($name))
                . pack('v', 0) // extra data length
                . pack('v', 0) // file comment length
                . pack('v', 0) // disk number start
                . pack('v', 0) // internal file attributes
                . pack('V', 32) // external file attributes
                . pack('V', $offset) // offset
                . $name;

            $actualHeaderSize = strlen($str);
            if ($actualHeaderSize !== $headerSize) {
                throw new \LogicException("CDS size miscalculated. Expected $headerSize, got $actualHeaderSize for $name.");
            }

            return $str;
        };

        return new LazyCallbackStream($callback, $headerSize);
    }

    public function close()
    {
        $this->stream->close();
        $this->locked = false;
        $this->files = [];
        $this->crc32 = [];
    }

    public function detach()
    {
        $this->stream->detach();
        $this->locked = false;
        $this->files = [];
        $this->crc32 = [];
    }

    public function isWritable()
    {
        return false;
    }

    public function write($string)
    {
        throw new \RuntimeException("ZipStream is not writable.");
    }
}
