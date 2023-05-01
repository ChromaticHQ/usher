<?php

namespace Usher\Robo\Plugin\Traits;

use Robo\Exception\TaskException;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait to provide site configuration functionality to Robo commands.
 */
trait SitesConfigTrait
{
    /**
     * Filename for a site's configuration file.
     *
     * @var string
     */
    protected $sitesConfigFile = '.sites.config.yml';

    /**
     * Load configuration for all sites.
     *
     * @return mixed[]
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
     * @return string[]
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
     * @return mixed[]
     *   The specified site configuration array.
     */
    protected function getSiteConfig($siteName = 'default'): array
    {
        $allSitesConfig = $this->getAllSitesConfig();
        if (!is_array($allSitesConfig[$siteName])) {
            throw new TaskException(
                $this,
                "Configuration for '$siteName' missing or malformed in $this->sitesConfigFile."
            );
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
    public function getConfig($key, $siteName = 'default'): mixed
    {
        $siteConfig = $this->getSiteConfig($siteName);
        if (!isset($siteConfig[$key])) {
            throw new TaskException($this, "Key $key not found for '$siteName' in $this->sitesConfigFile.");
        }
        return $siteConfig[$key];
    }

    /**
     * Write sites configuration file.
     *
     * @param string[] $sitesConfig
     *   An array of site config data to be written as Yaml.
     */
    protected function writeSitesConfig(array $sitesConfig): void
    {
        ksort($sitesConfig);
        file_put_contents($this->sitesConfigFile, Yaml::dump($sitesConfig));
    }
}
