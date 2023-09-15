<?php

namespace Usher\Robo\Task\Discovery;

trait Tasks
{
    /**
     * @param string|string[] $dirs
     *
     * @return \Usher\Robo\Task\Discovery\Alternatives|\Robo\Collection\CollectionBuilder
     */
    protected function taskAlternatives($dirs)
    {
        return $this->task(Alternatives::class, $dirs);
    }
}
