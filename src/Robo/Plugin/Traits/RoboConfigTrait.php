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
     *
     * @return array
     *   A configuration array.
     */
    protected function getRequiredRoboConfigArrayFor(string $key): array
    {
        $configValue = $this->getRequiredRoboConfigValueFor($key);
        $this->validateRoboConfigValueMatchesType($configValue, ConfigTypes::array, $key);
        return $configValue;
    }

    /**
     * Get Robo configuration string value.
     *
     * @param string $key
     *   The key of the configuration to load.
     *
     * @return string
     *   A configuration string.
     */
    protected function getRequiredRoboConfigStringFor(string $key): string
    {
        $configValue = $this->getRequiredRoboConfigValueFor($key);
        $this->validateRoboConfigValueMatchesType($configValue, ConfigTypes::string, $key);
        return $configValue;
    }

    /**
     * Get Robo configuration boolean value.
     *
     * @param string $key
     *   The key of the configuration to load.
     *
     * @return bool
     *   A configuration value.
     */
    protected function getRequiredRoboConfigBoolFor(string $key): bool
    {
        $configValue = $this->getRequiredRoboConfigValueFor($key);
        $this->validateRoboConfigValueMatchesType($configValue, ConfigTypes::boolean, $key);
        return $configValue;
    }

    /**
     * Get Robo configuration value.
     *
     * @param string $key
     *   The key of the configuration to load.
     *
     * @return mixed
     *   A configuration value.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function getRequiredRoboConfigValueFor(string $key)
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
     * @param mixed $configValue
     *   The configuration value.
     * @param ConfigTypes $expectedType
     *   The type we are expecting the value to be of.
     * @param string $key
     *   The key of the configuration.
     *
     * @return bool
     *   TRUE if configuration value matches the expected type.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function validateRoboConfigValueMatchesType($configValue, ConfigTypes $expectedType, string $key): bool
    {
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
