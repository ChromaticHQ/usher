<?php

namespace Usher\Robo\Task\Discovery;

use Robo\Task\BaseTask;
use Robo\Result;

/**
 * Use this to figure out which binary to use from a series of alternatives.
 *
 * ``` php
 * <?php
 * // Figure out whether you should use more or less or cat on a system. The
 * // result data will always contain a 'path' property, even if no alternative
 * // could be resolved and the path is empty.
 * $result = $this->task(Alternatives::class, 'more', 'less,cat')->run();
 * $binary = $result->getData()['path']
 * ?>
 * ```
 */
class Alternatives extends BaseTask
{
    /**
     * @var string
     */
    protected string $alternatives;

    /**
     * @var string
     */
    protected string $command;

    /**
     * @var int
     */
    protected const SHELL_SUCCESS = 0;

    /**
     * Alternatives constructor.
     *
     * @param string $command
     *   The preferred binary or path to binary to execute.
     * @param string $alternatives
     *   A comma-separated list of alternative binaries or paths to binaries to
     *   execute.
     */
    public function __construct(string $command, string $alternatives)
    {
        $this->command = $command;
        $this->alternatives = $alternatives;
    }

    /**
     * {@inheritdoc}
     *
     * The array returned by Result::getData() will always contain a 'path'
     *   property, even if empty.
     */
    public function run(): Result
    {
        $this->printTaskInfo("Resolving binary for {$this->command}...");
        if (@file_exists($this->command) && is_executable($this->command)) {
            return Result::success($this, "Found {$this->command}", ['path' => $this->command]);
        }
        $output = [];
        $result_code = NULL;
        $alternatives = explode(',', $this->alternatives);
        array_unshift($alternatives, $this->command);
        foreach ($alternatives as $alternative) {
            $arg = escapeshellarg($alternative);
            exec("which $arg", $output, $result_code);
            if ($result_code === static::SHELL_SUCCESS) {
                return Result::success($this, "Resolved to $alternative", ['path' => current($output)]);
            }
        }
        $this->printTaskWarning('Could not resolve any of the executables suggested.');
        return Result::cancelled('Could not resolve any of the executables suggested.', ['path' => '']);
    }

}
