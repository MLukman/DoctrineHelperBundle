<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FromUploadedFileInterface
{

    public static function fromUploadedFile(UploadedFile $file): ?static;
}