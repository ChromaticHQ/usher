<?php

namespace Usher\Robo\Plugin\Commands;

use Robo\Exception\TaskException;
use Robo\Result;
use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;
use Usher\Robo\Plugin\Traits\RoboConfigTrait;
use Usher\Robo\Plugin\Traits\SitesConfigTrait;

/**
 * Robo commands related to modifying tooling config files.
 */
class ToolingCommands extends Tasks
{
    use RoboConfigTrait;
    use SitesConfigTrait;

    /**
     * The current working directory.
     *
     * @var string
     */
    protected $cwd;

    /**
     * Robo task constructor.
     */
    public function __construct()
    {
        // Treat this command like bash -e and exit as soon as there's a failure.
        $this->stopOnFail();
        $this->cwd = getcwd();
    }

    /**
     * Update PHP version in configuration files.
     *
     * @param string $version
     *   The PHP version.
     * @param array $opts
     *   The options.
     *
     * @option boolean $skip-composer-update Skip composer update.
     *
     * @return \Robo\Result
     *   The result of the set of tasks.
     *
     * @throws \Robo\Exception\TaskException
     */
    public function configUpdatePhpVersion(string $version, array $opts = ['skip-composer-update' => false]): Result
    {
        $this->io()->title("Updating PHP version.");

        $currentPhpVersion = $this->getRequiredRoboConfigStringFor('php_current_version');
        $this->say("Current PHP version: $currentPhpVersion");
        $this->say("New PHP version: $version");
        if ($currentPhpVersion == $version) {
            throw new TaskException($this, "New PHP version matches existing version: $currentPhpVersion.");
        }

        $configFilePaths = array_map(
            fn(string $path): string => "$this->cwd/$path",
            $this->getRequiredRoboConfigArrayFor('php_version_config_paths')
        );

        $result = Result::cancelled();
        $composerFilename = 'composer.json';
        foreach ($configFilePaths as $configPath) {
            $result = $this->taskReplaceInFile($configPath)
                ->from($currentPhpVersion)
                ->to($version)
                ->run();

            if (str_contains($configPath, $composerFilename)) {
                $this->say("Change to $composerFilename detected.");
                if ($opts['skip-composer-update']) {
                    $this->yell("'composer update' skipped.");
                    continue;
                }
                $this->yell('Updating composer.lock file.');
                $this->taskComposerUpdate()
                    ->workingDir(rtrim($configPath, $composerFilename))
                    ->option('lock')
                    ->run();
                $result = $this->taskComposerValidate()->run();
            }
        }
        $this->updateRoboConfig('php_current_version', $version);
        $this->yell("PHP version updated from $currentPhpVersion to $version.");
        return $result;
    }

    /**
     * Update Robo config file.
     *
     * @param string $key
     *   The key to update.
     * @param string $value
     *   The value to set it to.
     */
    protected function updateRoboConfig(string $key, string $value): void
    {
        $roboConfigPath = "$this->cwd/robo.yml";
        $roboConfig = Yaml::parse(file_get_contents($roboConfigPath));
        $roboConfig[$key] = $value;
        file_put_contents($roboConfigPath, Yaml::dump($roboConfig));
    }
}
