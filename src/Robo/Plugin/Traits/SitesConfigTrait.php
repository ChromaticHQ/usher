<?php

namespace ChqRobo\Robo\Plugin\Traits;

use Robo\Exception\TaskException;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait to provide site configuration functionality to Robo commands.
 */
trait SitesConfigTrait
{

    protected $sitesConfigFile = '.sites.config.yml';

    /**
     * Load configuration for all sites.
     *
     * @return array
     *   A configuration array for all sites.
     */
    public function getAllSitesConfig(): array
    {
        if (!file_exists($this->sitesConfigFile)) {
            throw new TaskException($this, "$this->sitesConfigFile not found.");
        }
        return Yaml::parseFile($this->sitesConfigFile);
    }

    /**
     * Get all site names from configuration.
     *
     * @return array
     *   An array of all site names.
     */
    public function getAllSiteNames(): array
    {
        return array_keys($this->getAllSitesConfig());
    }

    /**
     * Determine how many sites are included in sites config.
     *
     * @return int
     *   The number of sites.
     */
    public function getSitesCount(): int
    {
        return count($this->getAllSitesConfig());
    }

    /**
     * Load sites configuration.
     *
     * @param string $siteName
     *   The site name.
     *
     * @return array
     *   The specified site configuration array.
     */
    protected function getSiteConfig($siteName = 'default'): array
    {
        $allSitesConfig = $this->getAllSitesConfig();
        if (empty($allSitesConfig[$siteName])) {
            throw new TaskException($this, "Configuration for '$siteName' not found in $this->sitesConfigFile.");
        }
        return $allSitesConfig[$siteName];
    }

    /**
     * Get site configuration value.
     *
     * @param string $key
     *   The site configuration key to load.
     * @param string $siteName
     *   The site name.
     *
     * @return mixed
     *   A configuration value.
     */
    public function getConfig($key, $siteName = 'default')
    {
        $siteConfig = $this->getSiteConfig($siteName);
        if (empty($siteConfig[$key])) {
            throw new TaskException($this, "Key $key not found for '$siteName' in $this->sitesConfigFile.");
        }
        return $siteConfig[$key];
    }

    /**
     * Write sites configuration file.
     */
    public function writeSitesConfig(array $sitesConfig)
    {
        ksort($sitesConfig);
        file_put_contents($this->sitesConfigFile, Yaml::dump($sitesConfig));
    }
}
