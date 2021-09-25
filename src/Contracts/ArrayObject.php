<?php

namespace SwooleTW\Http\Contracts;

class ArrayObject extends \Swoole\ArrayObject implements \Stringable
{

    public function __toString()
    {
        return serialize($this);
    }
}