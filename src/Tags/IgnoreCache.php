<?php

namespace Kreatif\StatamicFragmentCache\Tags;
use Statamic\Tags\Tags;

class IgnoreCache extends Tags
{

    protected static $handle = 'ignore_cache';

    /**
     * @Usage: {{ ignore_cache }}
     * @return mixed
     */
    public function index()
    {
        return $this->content;
    }
}
