<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Exception;
use finfo;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use InvalidArgumentException;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Serializable;
use Stringable;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ImageWrapper implements Serializable, JsonSerializable, Stringable, FromUploadedFileInterface
{
    public const RESIZE_FIT = 'fit';
    public const RESIZE_CROP = 'crop';
    public const RESIZE_STRETCH = 'stretch';

    /**
     * @var string The default format of the image (either png or jpeg) for newly created instances
     */
    private static string $defaultOutputFormat = 'png';

    /**
     * @var AbstractImagine
     */
    private ?AbstractImagine $imagine = null;

    /**
     * @var ImageInterface $image
     */
    private ?ImageInterface $image = null;

    /**
     * @var string The mime type of the image
     */
    public string $mimetype;

    /**
     *
     * @var string The base64 content of the image
     */
    public string $base64 = '';

    /**
     *
     * @var int default maximum width & height in pixels
     */
    private int $defaultMaxWidth = 200;
    private int $defaultMaxHeight = 200;

    /**
     *
     * @var string The download link for the image that can be set by the application
     */
    private ?string $downloadLink = null;

    private bool $mightBeModified = false;

    /**
     * Construct from either image stream or filename or Imagine\Image\ImageInterface
     * @param resource|string|ImageInterface|callable $source
     * @param string $outputFormat
     */
    public function __construct(
        private mixed $source = null,
        private string $outputFormat = '',
        private string $engineType = 'gd',
        private ?string $uuid = null
    ) {
        if (empty($this->source) && empty($this->uuid)) {
            throw new InvalidArgumentException('At least one of source or uuid must be set');
        }
        if (empty($this->outputFormat)) {
            $this->outputFormat  = static::$defaultOutputFormat;
        }
        if (!empty($this->source)) {
            $this->mightBeModified = true;
        }
    }

    protected function engine(): AbstractImagine
    {
        if (!$this->imagine) {
            switch (strtolower($this->engineType)) {
                case 'gmagick':
                    $this->imagine = new \Imagine\Gmagick\Imagine();
                    break;
                case 'imagick':
                    $this->imagine = new \Imagine\Imagick\Imagine();
                    break;
                default:
                    $this->imagine = new \Imagine\Gd\Imagine();
            }
        }
        return $this->imagine;
    }

    public function setDefaultMaxWidthHeight(int $defaultMaxWidth, int $defaultMaxHeight): void
    {
        $this->defaultMaxWidth = $defaultMaxWidth;
        $this->defaultMaxHeight = $defaultMaxHeight;
    }

    public function setSource(mixed $source): self
    {
        if (!empty($this->source)) {
            $this->mightBeModified = true;
        }
        $this->source = $source;
        return $this;
    }

    public function load(): self
    {
        if ($this->source instanceof ImageInterface) {
            $this->image = $this->source;
        } elseif (is_resource($this->source)) {
            rewind($this->source);
            $this->image = $this->engine()->read($this->source);
            rewind($this->source);
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->buffer(stream_get_contents($this->source));
            if (substr($mime, 0, 6) == 'image/') {
                $this->outputFormat = substr($mime, 6);
            }
        } elseif (is_string($this->source) && file_exists($this->source)) {
            $this->image = $this->engine()->open($this->source);
            $mime = mime_content_type($this->source);
            if (substr($mime, 0, 6) == 'image/') {
                $this->outputFormat = substr($mime, 6);
            }
        } elseif (is_callable($this->source)) {
            $this->source = call_user_func($this->source);
            if (!is_callable($this->source)) {
                $this->load();
            }
        }

        return $this;
    }

    public function open($filename): self
    {
        $this->setSource($filename);
        $this->load();
        return $this;
    }

    public function loadResource($resource): self
    {
        $this->setSource($resource);
        $this->load();
        return $this;
    }

    protected function refreshProperties(): self
    {
        $this->base64 = base64_encode($this->get($this->outputFormat));
        $this->mimetype = 'image/' . $this->outputFormat;
        return $this;
    }

    public function setOutputFormat(string $format): void
    {
        $this->outputFormat = $format;
        $this->refreshProperties();
    }

    public function get(?string $format = null): ?string
    {
        if (!($image = $this->getImage())) {
            return null;
        }
        return $image->get($format ?: $this->outputFormat);
    }

    public function getImage(): ?ImageInterface
    {
        if (!$this->image) {
            $this->load();
        }
        return $this->image;
    }

    public function resize(int $maxWidth = 0, int $maxHeight = 0, string $resizeMode = self::RESIZE_FIT): self
    {
        if (!($image = $this->getImage())) {
            return $this;
        }

        $size = $image->getSize();
        $oriWidth = $size->getWidth();
        $oriHeight = $size->getHeight();
        $ratio = $oriWidth / $oriHeight;
        $targetWidth = $maxWidth > 0 ? $maxWidth : $this->defaultMaxWidth;
        $targetHeight = $maxHeight > 0 ? $maxHeight : ($maxWidth > 0 ? $maxWidth : $this->defaultMaxHeight);
        $targetRatio = $targetWidth / $targetHeight;

        switch ($resizeMode) {
            case static::RESIZE_STRETCH:
                $width = $targetWidth;
                $height = $targetHeight;
                break;

            case static::RESIZE_CROP:
                if ($targetRatio > $ratio) {
                    $newHeight = $oriWidth / $targetRatio;
                    $image->crop(
                        new Point(0, ($oriHeight - $newHeight) / 2),
                        new Box($oriWidth, $newHeight)
                    );
                } elseif ($targetRatio < $ratio) {
                    $newWidth = $oriHeight * $targetRatio;
                    $image->crop(
                        new Point(($oriWidth - $newWidth) / 2, 0),
                        new Box($newWidth, $oriHeight)
                    );
                }
                $width = $targetWidth;
                $height = $targetHeight;
                break;
            case static::RESIZE_FIT:
            default:
                if ($targetRatio > $ratio) {
                    $height = $targetHeight;
                    $width = $targetHeight * $ratio;
                } else {
                    $width = $targetWidth;
                    $height = $targetWidth / $ratio;
                }
                break;
        }

        if ($width != $oriWidth || $height != $oriHeight) {
            $image->resize(new Box($width, $height));
            $this->refreshProperties();
        }
        return $this;
    }

    public function getWidth(): int
    {
        return ($image = $this->getImage()) ? $image->getSize()->getWidth() : 0;
    }

    public function getHeight(): int
    {
        return ($image = $this->getImage()) ? $image->getSize()->getHeight() : 0;
    }

    public function __serialize(): array
    {
        return ['mimetype' => $this->mimetype ?? null, 'base64' => $this->base64 ?? null];
    }

    // For Serializable
    public function serialize(): string
    {
        return \json_encode($this->__serialize());
    }

    public function __unserialize(array $serialized): void
    {
        if (isset($serialized['base64']) && !empty($serialized['base64'])) {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, base64_decode($serialized['base64'], true));
            rewind($stream);
            $this->loadResource($stream);
            fclose($stream);
        }
    }

    // For Serializable
    public function unserialize(string $serialized): void
    {
        $this->__unserialize(\json_decode($serialized, true));
    }

    // For JsonSerializable
    public function jsonSerialize(): mixed
    {
        return $this->downloadLink ?: $this->__serialize();
    }

    // For Stringable
    public function __toString(): string
    {
        return $this->downloadLink ?: $this->getBase64DataUrl();
    }

    public function getBase64DataUrl(): string
    {
        $s = $this->__serialize();
        return "data:{$s['mimetype']};base64,{$s['base64']}";
    }

    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }

    public function getBase64(): ?string
    {
        return $this->base64;
    }

    public function setBase64(?string $base64): void
    {
        $this->base64 = $base64;
        $this->__unserialize($this->__serialize());
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
        $binary = $this->get();
        $response = new Response($binary, 200, [
            'Content-Type' => $this->getMimetype(),
            'Content-Length' => strlen($binary),
            'Content-Disposition' => ResponseHeaderBag::DISPOSITION_INLINE,
        ]);
        $response->setEtag(md5($binary));
        $response->setPublic();
        if ($request) {
            $response->isNotModified($request);
        }
        return $response;
    }

    public function checksum(): ?string
    {
        return ($content = $this->get()) ? sha1($content) : null;
    }

    public function uuid(): string
    {
        if (!$this->uuid) {
            $this->uuid = Uuid::uuid7();
        }
        return $this->uuid;
    }

    public function mightBeModified(): bool
    {
        return $this->mightBeModified;
    }

    public function getOutputFormat(): string
    {
        return $this->outputFormat;
    }

    public static function getDefaultOutputFormat(): string
    {
        return self::$defaultOutputFormat;
    }

    public static function setDefaultOutputFormat(string $defaultOutputFormat): void
    {
        self::$defaultOutputFormat = $defaultOutputFormat;
    }

    public static function fromUploadedFile(UploadedFile $file): ?static
    {
        try {
            return new self($file->getRealPath());
        } catch (Exception $ex) {
            return null;
        }
    }
}
