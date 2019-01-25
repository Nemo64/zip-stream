<?php

namespace Nemo64\ZipStream;


use function GuzzleHttp\Psr7\stream_for;
use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class LazyCallbackStream implements StreamInterface
{
    use StreamDecoratorTrait;

    /**
     * @var callable
     */
    private $callable;

    /**
     * @var int
     */
    private $size;

    public function __construct(callable $callable, int $size = null)
    {
        $this->callable = $callable;
        $this->size = $size;
    }

    protected function createStream()
    {
        return stream_for(call_user_func($this->callable));
    }

    public function getSize()
    {
        return $this->size ?? $this->stream->getSize();
    }
}
