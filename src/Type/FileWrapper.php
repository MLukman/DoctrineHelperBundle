<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Stringable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FileWrapper implements FromUploadedFileInterface, Stringable
{
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
     * @var string The download link for the file that can be set by the application
     */
    private ?string $downloadLink = null;

    public function __construct(?string $name, int $size, ?string $mimetype)
    {
        $this->name = $name;
        $this->size = $size;
        $this->mimetype = $mimetype;
    }

    public function __destruct()
    {
        if ($this->stream) {
            fclose($this->stream);
        }
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

    public function getContent(): string|false
    {
        rewind($this->stream);
        return stream_get_contents($this->stream);
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

    public static function fromUploadedFile(UploadedFile $file): ?static
    {
        if (!$file->isValid()) {
            return null;
        }
        $filestore = new self($file->getClientOriginalName(), $file->getSize(), $file->getMimeType());
        $filestore->setStream(fopen($file->getRealPath(), 'r'));
        return $filestore;
    }

    public function __toString(): string
    {
        return $this->downloadLink ?: $this->name ?: "Unknown";
    }
}