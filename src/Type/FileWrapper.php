<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Closure;
use Ramsey\Uuid\Uuid;
use Stringable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FileWrapper implements FromUploadedFileInterface, Stringable
{
    /**
     *
     * @var string UUID
     */
    protected ?string $uuid = null;

    /**
     *
     * @var string The file name
     */
    protected ?string $name = null;

    /**
     *
     * @var string The file size in bytes
     */
    protected int $size = 0;

    /**
     *
     * @var string The file mime type
     */
    protected ?string $mimetype = null;

    /**
     *
     * @var string The stream that contains the content of the file
     */
    protected $stream = null;

    /**
     *
     * @var Closure The callback that needs to be call to get content stream. 
     * Use this if the stream capturing can be postponed, e.g. fopen() on files
     */
    protected ?Closure $streamCallback = null;

    /**
     *
     * @var string The download link for the file that can be set by the application
     */
    private ?string $downloadLink = null;

    public function __construct(
            ?string $name, int $size, ?string $mimetype, string $uuid = null
    ) {
        $this->name = $name;
        $this->size = $size;
        $this->mimetype = $mimetype;
        $this->uuid = $uuid ?: Uuid::uuid7();
    }

    public function __destruct()
    {
        if ($this->stream) {
            fclose($this->stream);
        }
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }

    public function getStream()
    {
        if (!$this->stream && $this->streamCallback) {
            $this->stream = call_user_func($this->streamCallback);
        }
        return $this->stream;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    public function setMimetype(?string $mimetype): void
    {
        $this->mimetype = $mimetype;
    }

    public function setStream($stream): void
    {
        $this->stream = $stream;
    }

    public function getStreamCallback(): ?Closure
    {
        return $this->streamCallback;
    }

    public function setStreamCallback(?Closure $streamCallback): void
    {
        $this->streamCallback = $streamCallback;
    }

    public function getContent(): string|false
    {
        $stream = $this->getStream();
        rewind($stream);
        return stream_get_contents($stream);
    }

    public function setContent(string $content): void
    {
        if ($this->stream) {
            fclose($this->stream);
        }
        $this->stream = fopen('php://temp', 'r+');
        fwrite($this->stream, $content);
        rewind($this->stream);
    }

    public function getDownloadLink(): ?string
    {
        return $this->downloadLink;
    }

    public function setDownloadLink(?string $downloadLink): void
    {
        $this->downloadLink = $downloadLink;
    }

    public function getDownloadResponse(?Request $request = null): Response
    {
        $binary = $this->getContent();
        $response = new Response($binary, 200, [
            'Content-Type' => $this->getMimetype(),
            'Content-Length' => strlen($binary),
            'Content-Disposition' => "inline; filename=\"{$this->getName()}\"",
        ]);
        $response->setEtag(md5($binary));
        $response->setPublic();
        if ($request) {
            $response->isNotModified($request);
        }
        return $response;
    }

    public static function fromUploadedFile(
            UploadedFile $file, ?self $existing = null
    ): ?static {
        if (!$file->isValid()) {
            return null;
        }
        $filestore = new self($file->getClientOriginalName(), $file->getSize(), $file->getMimeType(), $existing ? $existing->uuid : null);
        $filestore->setStream(fopen($file->getRealPath(), 'r'));
        return $filestore;
    }

    public function __toString(): string
    {
        return $this->downloadLink ?: $this->name ?: "Unknown";
    }
}
