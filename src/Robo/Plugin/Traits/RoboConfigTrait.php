<?php

namespace Usher\Robo\Plugin\Traits;

use Robo\Robo;
use Robo\Exception\TaskException;
use Symfony\Component\Yaml\Yaml;
use Usher\Robo\Plugin\Enums\ConfigTypes;

/**
 * Trait to provide access to Robo configuration.
 */
trait RoboConfigTrait
{
    protected array $defaultYamlConf = [
        'inline_level' => 10,
        'indent' => 2,
        'dump_bits' => Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE |
            Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE |
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK |
            Yaml::DUMP_NUMERIC_KEY_AS_STRING |
            Yaml::DUMP_OBJECT_AS_MAP,
    ];

    /**
     * Get the default YAML configuration.
     */
    protected function getDefaultYamlConf(): array
    {
        return $this->defaultYamlConf;
    }

    /**
     * Get Robo configuration value.
     *
     * @param string $key
     *   The key of the configuration to load.
     *
     * @throws \Robo\Exception\TaskException
     */
    protected function getOptionalRoboConfigArrayFor(string $key): mixed
    {
        $configValue = Robo::config()->get($key);
        $this->validateRoboConfigValueMatchesType(
            configValue: $configValue,
            expectedType: ConfigTypes::array,
            key: $key,
        );
        return $configValue ?? [];
    }

    /**
     * Get Robo configuration array value.
     *
     * @param string $key
     *   The key of the configuration to load.
     */
    protected function getRequiredRoboConfigArrayFor(string $key): array
    {
        $configValue = $this->getRequiredRoboConfigValueFor(key: $key);
        $this->validateRoboConfigValueMatchesType(
            configValue: $configValue,
            expectedType: ConfigTypes::array,
            key: $key,
        );
        return $configValue;
    }

    /**
     * Get Robo configuration string value.
     *
     * @param string $key
     *   The key of the configuration to load.
     */
    protected function getRequiredRoboConfigStringFor(string $key): string
    {
        $configValue = $this->getRequiredRoboConfigValueFor(key: $key);
        $this->validateRoboConfigValueMatchesType(
            configValue: $configValue,
            expectedType: ConfigTypes::string,
            key: $key,
        );
        return $configValue;
    }

    /**
     * Get Robo configuration boolean value.
     *
     * @param string $key
     *   The key of the configuration to load.
     */
    protected function getRequiredRoboConfigBoolFor(string $key): bool
    {
        $configValue = $this->getRequiredRoboConfigValueFor(key: $key);
        $this->validateRoboConfigValueMatchesType(
            configValue: $configValue,
            expectedType: ConfigTypes::boolean,
            key: $key,
        );
        return $configValue;
    }

    /**
     * Get Robo configuration value.
     *
     * @param string $key
     *   The key of the configuration to load.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function getRequiredRoboConfigValueFor(string $key): mixed
    {
        $configValue = Robo::config()->get($key);
        if (!isset($configValue)) {
            throw new TaskException($this, "Required key $key not found in Robo config file robo.yml.");
        }
        return $configValue;
    }

    /**
     * Validate Robo configuration value type.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function validateRoboConfigValueMatchesType(
        mixed $configValue,
        ConfigTypes $expectedType,
        string $key,
    ): bool {
        $foundType = gettype($configValue);
        if ($foundType != $expectedType->name) {
            throw new TaskException(
                $this,
                "Key $key in Robo configuration does not match expected type: $expectedType->name. Found $foundType."
            );
        }
        return true;
    }

    /**
     * Write data as yaml to a file.
     *
     * @param string $filepath
     *   The file to write the data to.
     * @param string[] $data
     *   An array of data to be written out as yaml.
     */
    protected function writeYaml(string $filepath, array $data): void
    {
        $yaml_conf = $this->getOptionalRoboConfigArrayFor('yaml_conf');
        $yaml_conf = [...$this->getDefaultYamlConf(), ...$yaml_conf];
        file_put_contents(
            $filepath,
            "---\n" . Yaml::dump(
                $data,
                $yaml_conf['inline_level'],
                $yaml_conf['indent'],
                $yaml_conf['dump_bits'],
            )
        );
    }
}
