<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use JsonSerializable;

interface ResponseBodySourceInterface
{

    public function createResponseBody(): ?ResponseBody;
}