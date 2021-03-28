<?php

namespace ChqRobo\Robo\Plugin\Traits;

use Drupal\Component\Serialization\Yaml;
use Robo\Exception\TaskException;

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
    protected function getAllSitesConfig(): array
    {
        if (!file_exists($this->sitesConfigFile)) {
            throw new TaskException($this, "$this->sitesConfigFile not found.");
        }
        return Yaml::decode(file_get_contents($this->sitesConfigFile));
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
            throw new TaskException($this, "Configuration for '$siteName' not found.");
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
            throw new TaskException($this, "Key $key not found for '$siteName'.");
        }
        return $siteConfig[$key];
    }

    /**
     * Write site config file.
     */
    public function writeSiteConfig(array $sitesConfig)
    {
        ksort($sitesConfig);
        file_put_contents($this->sitesConfigFile, Yaml::encode($sitesConfig));
    }
}
