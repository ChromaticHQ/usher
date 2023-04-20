<?php

namespace Usher\Robo\Plugin\Traits;

/**
 * Trait to determine what version of Drupal is being run..
 */
trait DrupalVersionTrait
{
    protected function drupalVersionIsD7(string $drupalRootPath): bool
    {
        // @TODO: Implement check.
        return false;
    }
}
