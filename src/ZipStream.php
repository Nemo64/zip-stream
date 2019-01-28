<?php

namespace Nemo64\ZipStream;


use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use PHP\Math\BigInteger\BigInteger;
use Psr\Http\Message\StreamInterface;
use function GuzzleHttp\Psr7\stream_for;

class ZipStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /** @var StreamInterface[] */
    private $files = [];

    /** @var string[] */
    private $crc32 = [];

    /** @var bool */
    private $locked = false;

    /** @var BigInteger|null */
    private $bigSize = null;

    public function __construct()
    {
        // disable the constructor of the trait
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

        if (in_array($stream, $this->files, true)) {
            throw new \RuntimeException("The same stream can only be added once.");
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
        $cdsPosition = new BigInteger();
        $cdsSize = 0;

        foreach ($this->files as $fileName => $fileStream) {
            $fileStream->rewind();

            $headerStream = $this->getHeaderStream($fileName);
            $stream->addStream($headerStream);
            $stream->addStream($fileStream);

            $cdsStreams[] = $this->getCdsStream($fileName, $cdsPosition->getValue());
            $cdsPosition->add($headerStream->getSize() + $fileStream->getSize());
        }

        foreach ($cdsStreams as $cdsStream) {
            $stream->addStream($cdsStream);
            $cdsSize += $cdsStream->getSize();
        }

        $eofHeader = pack('VvvvvVVv',
            0x06054b50, // end of central file header signature
            0x0000, // this disk number
            0x0000, // number of disk with cdr
            count($this->files), // number of entries (on this disk)
            count($this->files), // number of entries
            $cdsSize, // cds size
            $cdsPosition->getValue(), // cds position
            0x0000 // zip file comment length
        );
        $stream->addStream(stream_for($eofHeader));

        $this->bigSize = $cdsPosition->add(strlen($eofHeader));
        return $stream;
    }

    private function getHeaderStream(string $name)
    {
        $headerSize = 30 + strlen($name);
        $callback = function () use ($name, $headerSize) {
            $header = pack('VvvvVVVVvv',
                0x04034b50, // local file header signature
                0x000A, // version needed to extract
                0x0008, // general purpose bit flag (defines UTF-8 filename here)
                0x0000, // compression method... in this case none
                0x00000000, // TODO dos timestamp
                $this->getCrc32($name), // crc32 of data
                $this->files[$name]->getSize(), // compressed size
                $this->files[$name]->getSize(), // uncompressed size ~ obviously the same if not compressed
                strlen($name),
                0x0000 // extra data length
            );
            return $header . $name;
        };

        return new LazyCallbackStream($callback, $headerSize);
    }

    private function getCdsStream(string $name, int $offset)
    {
        $headerSize = 46 + strlen($name);
        $callback = function () use ($name, $offset, $headerSize) {
            $header = pack('VvvvvVVVVvvvvvVV',
                0x02014b50, // central file header signature
                0x003F, // Ver 6.3, OS_FAT
                0x000A, // version needed to extract
                0x0008, // general purpose bit flag (defines UTF-8 filename here)
                0x0000, // compression method... in this case none
                0x00000000, // TODO dos timestamp
                $this->getCrc32($name), // crc32 of data
                $this->files[$name]->getSize(), // compressed size
                $this->files[$name]->getSize(), // uncompressed size ~ obviously the same if not compressed
                strlen($name),
                0x0000, // extra data length
                0x0000, // file comment length
                0x0000, // disk number start
                0x0000, // internal file attributes
                32, // external file attributes
                $offset // offset
            );
            return $header . $name;
        };

        return new LazyCallbackStream($callback, $headerSize);
    }

    /**
     * Returns the expected size of the stream.
     * If the size exceeds PHP_MAX_INT, than it will return a string with the expected size.
     * Note that zip64 isn't implemented which means some programs might not be able to open the file.
     *
     * @return int|string
     */
    public function getSize()
    {
        $size = $this->stream->getSize();

        if (!is_int($size)) {
            $size = $this->bigSize->getValue();
        }

        return $size;
    }

    public function close()
    {
        if ($this->locked) {
            $this->stream->close();
            $this->locked = false;
            unset($this->stream);
        }

        $this->files = [];
        $this->crc32 = [];
    }

    public function detach()
    {
        if ($this->locked) {
            $this->stream->detach();
            $this->locked = false;
            unset($this->stream);
        }

        $this->files = [];
        $this->crc32 = [];
    }
}
