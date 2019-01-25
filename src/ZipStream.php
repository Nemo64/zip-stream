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
            throw new \RuntimeException("Filename too long: $name", 1545244717);
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
            throw new \RuntimeException("Files larger than 4GB ($name) are not implemented.", 1545244929);
        }

        if (count($this->files) >= 65535) {
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

    private static function pack(...$values)
    {
        return pack(implode("", array_column($values, 0)), ...array_column($values, 1));
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
            self::pack(
                ['V', 0x06054b50], // end of central file header signature
                ['v', 0x00], // this disk number
                ['v', 0x00], // number of disk with cdr
                ['v', count($this->files)], // number of entries (on this disk)
                ['v', count($this->files)], // number of entries
                ['V', $cdsSize], // cds size
                ['V', $cdsPosition], // cds position
                ['v', 0x00] // zip file comment length
            )
        ));

        return $stream;
    }

    private function getHeaderStream(string $name)
    {
        $headerSize = 30 + strlen($name);
        $callback = function () use ($name, $headerSize) {
            $header = self::pack(
                ['V', 0x04034b50], // local file header signature
                ['v', 0x000A], // version needed to extract
                ['v', 0x08], // general purpose bit flag (defines UTF-8 filename here)
                ['v', 0x00], // compression method... in this case none
                ['V', 0], // TODO dos timestamp
                ['V', $this->getCrc32($name)], // crc32 of data
                ['V', $this->files[$name]->getSize()], // compressed size
                ['V', $this->files[$name]->getSize()], // uncompressed size ~ obviously the same if not compressed
                ['v', strlen($name)],
                ['v', 0x00] // extra data length
            );
            return $header . $name;
        };

        return new LazyCallbackStream($callback, $headerSize);
    }

    private function getCdsStream(string $name, int $offset)
    {
        $headerSize = 46 + strlen($name);
        $callback = function () use ($name, $offset, $headerSize) {
            $header = self::pack(
                ['V', 0x02014b50], // central file header signature
                ['v', 0x003F], // Ver 6.3, OS_FAT
                ['v', 0x000A], // version needed to extract
                ['v', 0x08], // general purpose bit flag (defines UTF-8 filename here)
                ['v', 0x00], // compression method... in this case none
                ['V', 0], // TODO dos timestamp
                ['V', $this->getCrc32($name)], // crc32 of data
                ['V', $this->files[$name]->getSize()], // compressed size
                ['V', $this->files[$name]->getSize()], // uncompressed size ~ obviously the same if not compressed
                ['v', strlen($name)],
                ['v', 0], // extra data length
                ['v', 0], // file comment length
                ['v', 0], // disk number start
                ['v', 0], // internal file attributes
                ['V', 32], // external file attributes
                ['V', $offset] // offset
            );
            return $header . $name;
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
