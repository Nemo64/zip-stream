[![Build Status](https://travis-ci.org/Nemo64/zip-stream.svg?branch=master)](https://travis-ci.org/Nemo64/zip-stream)
[![Latest Stable Version](https://poser.pugx.org/Nemo64/zip-stream/v/stable)](https://packagist.org/packages/Nemo64/zip-stream)
[![Total Downloads](https://poser.pugx.org/Nemo64/zip-stream/downloads)](https://packagist.org/packages/Nemo64/zip-stream)
[![Monthly Downloads](https://poser.pugx.org/Nemo64/zip-stream/d/monthly)](https://packagist.org/packages/Nemo64/zip-stream)
[![License](https://poser.pugx.org/Nemo64/zip-stream/license)](https://packagist.org/packages/Nemo64/zip-stream)

# ZIP Stream

This library allows you to create an PSR-7 stream which contains multiple files in a zip format.
This is done without actually creating a zip file at any time and is therefore very light on resources.
In fact, there should be very little difference compared to just sending the file though php.

Here are some special characteristics:

- no files are created so no cleanup is nessesary
- the length of the file is known before sending it making a `Content-Length` header possible (which let's the user know how long the download takes)
- it is possible to resume a download if your psr7-emitter/framework supports it.
- you don't have to output the stream to the browser, for example you can stream it using guzzle in a post request.
- there are no platform dependencies. You don't need `ext-zip` or the zip command on your machine.

But there are also some limitations:

- The created zip file has no compression at all. This is necessary for the quick size calculation. If you need that use [maennchen/zipstream-php](https://github.com/maennchen/zipstream-php)
- There is no Zip64 implementation yet so you are limited to 4GB files. There might be additional limitations in 32 bit php which I haven't investigated yet.

## Example

You'll need a way to send PSR-7 Response objects to the client.
Some Frameworks start to support this but in the mean time i'll recommend you use an external library like [arrowspark/http-emitter](https://github.com/narrowspark/http-emitter) to do just that.

```PHP
use function GuzzleHttp\Psr7\stream_for;
use Narrowspark\HttpEmitter\SapiStreamEmitter;
use Nemo64\ZipStream\ZipResponse;
use Nemo64\ZipStream\ZipStream;

$zip = new ZipStream();
$zip->add('file1.jpg', stream_for('file1.jpg'));

// be sure that before you send this response that there is no output buffering engaged.
while (@ob_end_clean()) {}

$response = ZipResponse::create($zip, 'my_archive.zip');
$emitter = new SapiStreamEmitter();
$emitter->emit($response);
```
