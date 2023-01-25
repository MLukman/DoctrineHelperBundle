<?php

namespace MLukman\DoctrineHelperBundle\DTO;

interface ResponseBodySourceInterface
{

    public function createResponseBody(): ?ResponseBody;
}