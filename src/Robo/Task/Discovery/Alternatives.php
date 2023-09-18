<?php

namespace Usher\Robo\Task\Discovery;

use Robo\Task\BaseTask;
use Robo\Result;
use Robo\ResultData;

/**
 * Use this to figure out which binary to use from a series of alternatives.
 *
 * ``` php
 * <?php
 * // Figure out whether you should use more or less or cat on a system. The
 * // result data will always contain a 'path' property, even if no alternative
 * // could be resolved and the path is empty.
 * $result = $this->task(Alternatives::class, 'more', ['less', 'cat'])->run();
 * $binary = $result->getData()['path']
 * ?>
 * ```
 */
class Alternatives extends BaseTask
{
    /**
     * @var int
     */
    protected const SHELL_SUCCESS = 0;

    /**
     * Alternatives constructor.
     *
     * @param string $command
     *   The preferred binary or path to binary to execute.
     * @param array $alternatives
     *   A list of alternative binaries or paths to binaries to
     *   execute.
     */
    public function __construct(
        protected string $command,
        protected array $alternatives
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * The array returned by Result::getData() will always contain a 'path'
     *   property, even if empty.
     */
    public function run(): Result|ResultData
    {
        $this->printTaskInfo("Resolving binary for {$this->command}...");
        if (file_exists($this->command) && is_executable($this->command)) {
            return Result::success($this, "Found {$this->command}", ['path' => $this->command]);
        }
        $output = [];
        $resultCode = null;

        array_unshift($this->alternatives, $this->command);
        foreach ($this->alternatives as $alternative) {
            $arg = escapeshellarg((string) $alternative);
            exec("which $arg", $output, $resultCode);
            if ($resultCode === static::SHELL_SUCCESS) {
                return Result::success($this, "Resolved to $alternative", ['path' => current($output)]);
            }
        }
        $this->printTaskWarning('Could not resolve any of the executables suggested.');
        return Result::cancelled('Could not resolve any of the executables suggested.', ['path' => '']);
    }
}
