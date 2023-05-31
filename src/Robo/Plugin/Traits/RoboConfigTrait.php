<?php

namespace Usher\Robo\Plugin\Traits;

use Robo\Robo;
use Robo\Exception\TaskException;
use Usher\Robo\Plugin\Enums\ConfigTypes;

/**
 * Trait to provide access to Robo configuration.
 */
trait RoboConfigTrait
{
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
}
