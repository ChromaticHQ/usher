<?php

namespace Usher\Robo\Plugin\Commands;

use AsyncAws\S3\S3Client;
use DrupalFinder\DrupalFinder;
use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Robo;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;
use Usher\Robo\Plugin\Enums\LocalDevEnvironmentTypes;
use Usher\Robo\Plugin\Traits\SitesConfigTrait;

/**
 * Robo commands related to changing development modes.
 */
class DevelopmentModeCommands extends DevelopmentModeBaseCommands
{
    /**
     * Refreshes a development environment.
     *
     * Completely refreshes a development environment including running 'composer install', downloading
     * a database dump, importing it, running deployment commands, disabling front-end caches, and
     * providing a login link.
     *
     * @param string $siteName
     *   The Drupal site name.
     * @option db
     *   Provide a database dump instead of relying on the latest available.
     * @option environment-type
     *   Specify alternative (supported) environment type. See LocalDevEnvironmentTypes enum.
     *
     * @aliases magic
     */
    public function devRefresh(
        string $siteName = 'default',
        array $options = ['db' => '', 'environment-type' => 'ddev'],
    ): Result {
        ['db' => $dbPath, 'environment-type' => $environmentType] = $options;
        return $this->devRefreshDrupal(
            environmentType: LocalDevEnvironmentTypes::from($environmentType),
            siteName: $siteName,
            databasePath: $dbPath,
        );
    }

    /**
     * Refreshes development environments for *all* sites.
     *
     * Completely refreshes a development environment including running 'composer install', downloading
     * a database dump, importing it, running deployment commands, disabling front-end caches, and
     * providing a login link.
     *
     * Examples:
     *   dev:refresh-all ddev --skip-sites=common,example
     *
     * @option skip-sites
     *   A comma separated list of sites to skip.
     * @option environment-type
     *   Specify alternative (supported) environment type. See LocalDevEnvironmentTypes enum.
     */
    public function devRefreshAll(
        array $options = ['skip-sites' => '', 'environment-type' => 'ddev']
    ): Result {
        ['skip-sites' => $skipSites, 'environment-type' => $environmentType] = $options;
        $siteNames = $this->getAllSiteNames();
        $result = null;
        foreach ($siteNames as $siteName) {
            if (in_array($siteName, explode(separator: ',', string: (string) $skipSites), true)) {
                continue;
            }
            $result = $this->devRefreshDrupal(
                environmentType: LocalDevEnvironmentTypes::from($environmentType),
                siteName: $siteName,
            );
        }
        return $result;
    }

    /**
     * Enable front-end development mode.
     *
     * @param string $siteDir
     *   The Drupal site directory name.
     * @param array $opts
     *   The options.
     *
     * @option boolean $yes Default answers to yes.
     * @aliases fede
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     */
    public function frontendDevEnable(string $siteDir = 'default', array $opts = ['yes|y' => false])
    {
        return $this->frontendDevEnableDrupal($siteDir, $opts);
    }
}
