<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Closure;
use DateTime;
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
     * @var DateTime The datetime the file is uploaded/stored
     */
    protected ?DateTime $datetime = null;

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

    /**
     * 
     * @var bool True if the content of the file might be modified
     */
    private bool $mightBeModified = false;

    public function __construct(
        ?string $name,
        int $size,
        ?string $mimetype,
        ?string $uuid = null,
        ?DateTime $datetime = null
    ) {
        $this->name = $name;
        $this->size = $size;
        $this->mimetype = $mimetype;
        $this->uuid = $uuid ?: Uuid::uuid7();
        $this->datetime = $datetime;
    }

    public function __destruct()
    {
        $this->closeStream();
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

    public function getReadableSize(?string $unit = null): string
    {
        $size = $this->size;
        if ((!$unit && $size >= 1 << 30) || $unit == "GB") {
            return number_format($size / (1 << 30), 2) . " GB";
        }
        if ((!$unit && $size >= 1 << 20) || $unit == "MB") {
            return number_format($size / (1 << 20), 2) . " MB";
        }
        if ((!$unit && $size >= 1 << 10) || $unit == "KB") {
            return number_format($size / (1 << 10), 2) . " KB";
        }
        return number_format($size) . " bytes";
    }

    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }

    public function getDatetime(): ?DateTime
    {
        return $this->datetime;
    }

    public function getStream()
    {
        if ((!$this->stream || !is_resource($this->stream)) && $this->streamCallback) {
            $this->stream = call_user_func($this->streamCallback);
        }
        rewind($this->stream);
        $this->mightBeModified = true;
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
        $this->mightBeModified = true;
    }

    public function getStreamCallback(): ?Closure
    {
        $this->mightBeModified = true;
        return $this->streamCallback;
    }

    public function setStreamCallback(?Closure $streamCallback): void
    {
        $this->streamCallback = $streamCallback;
    }

    public function getContent(): string|false
    {
        $stream = $this->getStream();
        return stream_get_contents($stream, offset: 0);
    }

    public function setContent(string $content): void
    {
        $this->closeStream();
        $this->stream = fopen('php://temp', 'w+');
        fwrite($this->stream, $content);
        rewind($this->stream);
        $this->mightBeModified = true;
    }

    protected function closeStream(): void
    {
        if ($this->stream) {
            if (is_resource($this->stream)) {
                fclose($this->stream);
            }
            $this->stream = null;
        }
    }

    public function getDownloadLink(): ?string
    {
        return $this->downloadLink;
    }

    public function setDownloadLink(?string $downloadLink): void
    {
        $this->downloadLink = $downloadLink;
    }

    public function getDownloadResponse(?Request $request = null, ?string $filenameOverride = null): Response
    {
        $binary = $this->getContent();
        $response = new Response($binary, 200, [
            'Content-Type' => $this->getMimetype(),
            'Content-Length' => strlen($binary),
            'Content-Disposition' => sprintf('inline; filename="%s"', $filenameOverride ?: $this->getName()),
        ]);
        $response->setEtag(md5($binary));
        $response->setPublic();
        if ($request) {
            $response->isNotModified($request);
        }
        return $response;
    }

    public static function fromUploadedFile(UploadedFile $file, ?self $existing = null): ?static
    {
        if (!$file->isValid()) {
            return null;
        }
        $filestore = new self($file->getClientOriginalName(), $file->getSize(), $file->getMimeType(), $existing ? $existing->uuid : null, new DateTime());
        $filestore->setStream(fopen($file->getRealPath(), 'r'));
        return $filestore;
    }

    public function __toString(): string
    {
        return $this->downloadLink ?: $this->name ?: "Unknown";
    }

    public function mightBeModified(): bool
    {
        return $this->mightBeModified;
    }
}
