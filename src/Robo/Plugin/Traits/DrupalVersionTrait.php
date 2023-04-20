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
        // This line has not changed since 2013, so it seems safe to base our version check off of.
        // https://git.drupalcode.org/project/drupal/-/blame/7.x/COPYRIGHT.txt
        $copyrightString = 'All Drupal code is Copyright 2001 - 2013 by the original authors.';
        $copyrightPath = "$drupalRootPath/COPYRIGHT.txt";
        if (file_exists($copyrightPath) && $copyrightContents = file_get_contents($copyrightPath)) {
            if (str_contains($copyrightContents, $copyrightString)) {
                return true;
            }
        }
        return false;
    }
}
