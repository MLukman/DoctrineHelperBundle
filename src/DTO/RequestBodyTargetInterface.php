<?php

namespace MLukman\DoctrineHelperBundle\DTO;

/**
 * An interface that must be implemented by all classes that need to be be populated with values coming from RequestBody subclasses.
 * RequestBodySubclass --[populate]--> RequestBodyTargetInterfaceImplementingClass
 */
interface RequestBodyTargetInterface
{

}