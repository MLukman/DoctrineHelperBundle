<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Exception;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use JsonSerializable;
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
     * @var string The format of the image (either png or jpeg)
     */
    private ?string $ouputFormat = 'png';

    /**
     * @var AbstractImagine
     */
    private ?AbstractImagine $_imagine = null;
    private string $engineType = 'gd';

    /**
     * @var ImageInterface $image
     */
    private ?ImageInterface $_image = null;

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

    /**
     * Construct from either image stream or filename or Imagine\Image\ImageInterface
     * @param resource|string|ImageInterface $image
     * @param type $outputFormat
     */
    public function __construct(mixed $image = null, ?string $outputFormat = null, string $engineType = 'gd')
    {
        $this->engineType = $engineType;
        $this->ouputFormat = $outputFormat ?: self::$defaultOutputFormat;
        if (is_resource($image)) {
            $this->loadResource($image);
        } elseif (is_string($image) && file_exists($image)) {
            $this->open($image);
        } elseif ($image instanceof ImageInterface) {
            $this->_image = $image;
        }
    }

    protected function engine(): AbstractImagine
    {
        if (!$this->_imagine) {
            switch (strtolower($this->engineType)) {
                case 'gmagick':
                    $this->_imagine = new \Imagine\Gmagick\Imagine();
                    break;
                case 'imagick':
                    $this->_imagine = new \Imagine\Imagick\Imagine();
                    break;
                default:
                    $this->_imagine = new \Imagine\Gd\Imagine();
            }
        }
        return $this->_imagine;
    }

    public function setDefaultMaxWidthHeight(int $defaultMaxWidth, int $defaultMaxHeight): void
    {
        $this->defaultMaxWidth = $defaultMaxWidth;
        $this->defaultMaxHeight = $defaultMaxHeight;
    }

    public function open($filename): self
    {
        $this->_image = $this->engine()->open($filename);
        $this->refreshProperties();
        return $this;
    }

    public function loadResource($resource): self
    {
        $this->_image = $this->engine()->read($resource);
        $this->refreshProperties();
        return $this;
    }

    protected function refreshProperties(): self
    {
        $this->base64 = base64_encode($this->get($this->ouputFormat));
        $this->mimetype = 'image/' . $this->ouputFormat;
        return $this;
    }

    public function get(?string $format = null): ?string
    {
        if (!$this->_image) {
            return null;
        }
        return $this->_image->get($format ?: $this->ouputFormat);
    }

    public function getImage(): ?ImageInterface
    {
        return $this->_image;
    }

    public function resize(int $maxWidth = 0, int $maxHeight = 0, string $resizeMode = self::RESIZE_FIT): self
    {
        if (!$this->_image) {
            return $this;
        }

        $size = $this->_image->getSize();
        $oriWidth = $size->getWidth();
        $oriHeight = $size->getHeight();
        $ratio = $oriWidth / $oriHeight;
        $targetWidth = $maxWidth > 0 ? $maxWidth : $this->defaultMaxWidth;
        $targetHeight = $maxHeight > 0 ? $maxHeight :
            ($maxWidth > 0 ? $maxWidth : $this->defaultMaxHeight);
        $targetRatio = $targetWidth / $targetHeight;

        switch ($resizeMode) {
            case static::RESIZE_STRETCH:
                $width = $targetWidth;
                $height = $targetHeight;
                break;

            case static::RESIZE_CROP:
                if ($targetRatio > $ratio) {
                    $newHeight = $oriWidth / $targetRatio;
                    $this->_image->crop(
                        new Point(0, ($oriHeight - $newHeight) / 2),
                        new Box($oriWidth, $newHeight)
                    );
                } elseif ($targetRatio < $ratio) {
                    $newWidth = $oriHeight * $targetRatio;
                    $this->_image->crop(
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
            $this->_image->resize(new Box($width, $height));
            $this->refreshProperties();
        }
        return $this;
    }

    public function getWidth(): int
    {
        return $this->_image ? $this->_image->getSize()->getWidth() : 0;
    }

    public function getHeight(): int
    {
        return $this->_image ? $this->_image->getSize()->getHeight() : 0;
    }

    public function __serialize(): array
    {
        return ['mimetype' => $this->mimetype, 'base64' => $this->base64];
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
