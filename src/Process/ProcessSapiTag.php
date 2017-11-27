<?php

namespace Jaeger\Process;

use Jaeger\Tag\StringTag;

class ProcessSapiTag extends StringTag
{
    public function __construct()
    {
        parent::__construct('process.sapi', php_sapi_name());
    }
}
