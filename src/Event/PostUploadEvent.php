<?php

/*
 * Copyright (c) 2022 Heimrich & Hannot GmbH
 *
 * @license LGPL-3.0-or-later
 */

namespace HeimrichHannot\MultiFileUploadBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PostUploadEvent extends Event
{
    private array $paths;
    private string $table;
    private string $field;

    public function __construct(array $paths, $table, $field)
    {
        $this->paths = $paths;
        $this->table = $table;
        $this->field = $field;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getField(): string
    {
        return $this->field;
    }
}
