<?php

namespace MLukman\DoctrineHelperBundle\DTO;

/**
 * An interface that must be implemented by all classes that need to be converted into ResponseBody subclasses.
 * ResponseBodySourceInterfaceImplementingClass --[convert]--> ResponseBodySubclass
 */
interface ResponseBodySourceInterface
{
    /**
     * Create an instance of ResponseBody subclass that will hold the converted values from $this instance.
     * @return ResponseBody|null
     */
    public function createResponseBody(): ?ResponseBody;
}
