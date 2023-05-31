<?php

namespace Usher\Robo\Plugin\Traits;

/**
 * Trait to determine what version of Drupal is being run.
 */
trait DrupalVersionTrait
{
    /**
     * Check if version of Drupal in question is Drupal 7.
     *
     * @param string $drupalRootPath
     *   The path to the Drupal root.
     */
    protected function drupalVersionIsD7(string $drupalRootPath): bool
    {
        // @see https://git.drupalcode.org/project/drupal/-/blob/7.x/includes/bootstrap.inc
        $bootstrapPath = "$drupalRootPath/includes/bootstrap.inc";
        if (file_exists($bootstrapPath)) {
            include_once($bootstrapPath);
            if (defined('VERSION') && str_starts_with((string) VERSION, '7.')) {
                return true;
            }
        }
        return false;
    }
}
