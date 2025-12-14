<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;

enum ImageWrapperResizer: string
{
    case FIT = 'fit';
    case CROP = 'crop';
    case STRETCH = 'stretch';
    case BLURBG = 'blurbg';
    case SMART = 'smart';

    public function resize(ImageInterface $image, ImagineInterface $imagine, int $targetWidth, int $targetHeight, ColorInterface|string|null $bgColor = null): bool
    {
        if ($targetHeight == 0 || $targetWidth == 0) {
            return false;
        }

        $size = $image->getSize();
        $oriWidth = $size->getWidth();
        $oriHeight = $size->getHeight();
        $ratio = $oriWidth / $oriHeight;
        $targetRatio = $targetWidth / $targetHeight;
        if ($targetHeight == $oriHeight && $targetWidth == $oriWidth) {
            return false;
        }

        $mode = $this;
        if ($mode == ImageWrapperResizer::SMART) {
            if ($ratio == $targetRatio) {
                $mode = ImageWrapperResizer::STRETCH;
            } elseif (($ratio > 1 && $targetRatio > 1) || ($ratio < 1 && $targetRatio < 1)) {
                $mode = ImageWrapperResizer::CROP;
            } else {
                $mode = ImageWrapperResizer::BLURBG;
            }
        }

        switch ($mode) {
            case ImageWrapperResizer::STRETCH:
                $image->resize(new Box($targetWidth, $targetHeight));
                break;

            case ImageWrapperResizer::CROP:
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
                $image->resize(new Box($targetWidth, $targetHeight));
                break;

            case ImageWrapperResizer::BLURBG:
                if (empty($bgColor)) {
                    $bgColor = '#000000';
                }
                if (!($bgColor instanceof ColorInterface)) {
                    $bgColor = (new RGB())->color($bgColor, 75);
                }
                $targetSize = new Box($targetWidth, $targetHeight);
                $foreground = $image->thumbnail($targetSize, ImageInterface::THUMBNAIL_INSET);
                $overlay = $imagine->create($targetSize, $bgColor);
                $image->resize($targetSize);
                $image->paste($overlay, new Point(0, 0));
                $image->effects()->blur(20);
                $x = ($targetSize->getWidth() - $foreground->getSize()->getWidth()) / 2;
                $y = ($targetSize->getHeight() - $foreground->getSize()->getHeight()) / 2;
                $image->paste($foreground, new Point($x, $y));
                break;

            case ImageWrapperResizer::FIT:
            default:
                if ($targetRatio > $ratio) {
                    $height = $targetHeight;
                    $width = $targetHeight * $ratio;
                } else {
                    $width = $targetWidth;
                    $height = $targetWidth / $ratio;
                }
                $image->resize(new Box($width, $height));
                break;
        }

        return true;
    }
}
