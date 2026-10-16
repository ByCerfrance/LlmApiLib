<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Model;

enum ParameterPolicy: string
{
    case STRICT = 'strict';
    case OMIT_UNSUPPORTED = 'omit_unsupported';
}
