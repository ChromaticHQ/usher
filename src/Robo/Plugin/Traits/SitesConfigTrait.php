<?php

namespace Usher\Robo\Plugin\Traits;

use Robo\Exception\TaskException;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait to provide site configuration functionality to Robo commands.
 */
trait SitesConfigTrait
{
    use RoboConfigTrait;

    /**
     * Filename for a site's configuration file.
     *
     * @var string
     */
    protected string $sitesConfigFile = '.sites.config.yml';

    /**
     * Load configuration for all sites.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function getAllSitesConfig(): mixed
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
     * @throws \Robo\Exception\TaskException
     */
    public function getAllSiteNames(): array
    {
        // @todo Fix assumption that Yaml::parseFile always returns an array.
        return array_keys($this->getAllSitesConfig());
    }

    /**
     * Determine how many sites are included in sites config.
     *
     * @throws \Robo\Exception\TaskException
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
     * @throws \Robo\Exception\TaskException
     */
    protected function getSiteConfig(string $siteName = 'default'): mixed
    {
        $allSitesConfig = $this->getAllSitesConfig();
        if (
            !is_array($allSitesConfig)
            || !array_key_exists($siteName, $allSitesConfig)
            || !is_array($allSitesConfig[$siteName])
        ) {
            throw new TaskException(
                $this,
                "Sites configuration in $this->sitesConfigFile is missing or malformed."
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
     *
     * @throws \Robo\Exception\TaskException
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
        $this->writeYaml($this->sitesConfigFile, $sitesConfig);
    }

    /**
     * Get the Drupal site admin user ID.
     *
     * @throws \Robo\Exception\TaskException
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
