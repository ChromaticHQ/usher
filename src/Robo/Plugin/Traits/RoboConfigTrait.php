<?php

namespace Usher\Robo\Plugin\Traits;

use Robo\Robo;
use Robo\Exception\TaskException;

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
    protected function getRoboConfigArrayFor(string $key): array
    {
        $configValue = $this->getRoboConfigValueFor($key);
        $this->validateRoboConfigValueMatchesType($configValue, 'array', $key);
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
    protected function getRoboConfigStringFor(string $key): string
    {
        $configValue = $this->getRoboConfigValueFor($key);
        $this->validateRoboConfigValueMatchesType($configValue, 'string', $key);
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
    private function getRoboConfigValueFor(string $key)
    {
        $configValue = Robo::config()->get($key);
        if (!isset($configValue)) {
            throw new TaskException($this, "Key $key not found in Robo config file robo.yml.");
        }
        return $configValue;
    }

    /**
     * Validate Robo configuration value type.
     *
     * @param mixed $configValue
     *   The configuration value.
     * @param string $expectedType
     *   The type we are expecting the value to be of.
     * @param string $key
     *   The key of the configuration.
     *
     * @return bool
     *   TRUE if configuration value matches the expected type.
     *
     * @throws \Robo\Exception\TaskException
     */
    private function validateRoboConfigValueMatchesType(mixed $configValue, string $expectedType, string $key): bool
    {
        $foundType = gettype($configValue);
        if ($foundType != $expectedType) {
            throw new TaskException(
                $this,
                "Key $key in Robo configuration does not match expected type: $expectedType. Found $foundType."
            );
        }
        return true;
    }
}
