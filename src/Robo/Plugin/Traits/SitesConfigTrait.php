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
     */
    public function getSitesCount(): int
    {
        return count($this->getAllSitesConfig());
    }

    /**
     * Get the configuration for an entire site.
     *
     * @param string $siteName
     *   The site name.
     *
     * @return mixed[]
     *   The specified site configuration array.
     */
    protected function getSiteConfig(string $siteName = 'default'): array
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
     * Get an individual site configuration value.
     *
     * @param string $key
     *   The site configuration key to load.
     * @param string $siteName
     *   The site name.
     * @param bool $required
     *   Whether the config item is expected to always be present.
     */
    public function getSiteConfigItem(string $key, string $siteName = 'default', bool $required = true): mixed
    {
        $siteConfig = $this->getSiteConfig(siteName: $siteName);
        if (!isset($siteConfig[$key])) {
            if ($required) {
                throw new TaskException($this, "Key $key not found for '$siteName' in $this->sitesConfigFile.");
            }
            return null;
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

    /**
     * Get the Drupal site admin user ID.
     *
     * @param string $siteName
     *   The site name.
     *
     * @return int
     *   The Drupal admin user ID.
     */
    protected function getDrupalSiteAdminUid(string $siteName = 'default'): int
    {
        return $this->getSiteConfigItem(
            key: 'drupal_user_login_uid',
            siteName: $siteName,
            required: false,
            // @todo: Replace the use of '1' with a constant once we drop PHP
            // 8.1 support.
        ) ?? 1;
    }
}
