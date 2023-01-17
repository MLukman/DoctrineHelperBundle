<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class FileWrapper implements FromUploadedFileInterface
{
    protected ?string $name = null;
    protected int $size = 0;
    protected ?string $mimetype = null;
    protected $stream = null;

    public function __construct(?string $name, int $size, ?string $mimetype)
    {
        $this->name = $name;
        $this->size = $size;
        $this->mimetype = $mimetype;
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

    public function setContent(string $content)
    {
        if ($this->stream) {
            fclose($this->stream);
        }
        $this->stream = fopen('php://temp', 'r+');
        fwrite($this->stream, $content);
        rewind($this->stream);
    }

    public function getDownloadResponse(): Response
    {
        return new Response($this->getContent(), 200, [
            'Content-type' => $this->getMimetype(),
            'Content-Disposition' => "inline; filename=\"{$this->getName()}\"",
        ]);
    }

    public static function fromUploadedFile(UploadedFile $file): ?static
    {
        $filestore = new self($file->getClientOriginalName(), $file->getSize(), $file->getMimeType());
        $filestore->setStream(fopen($file->getRealPath(), 'r'));
        return $filestore;
    }
}