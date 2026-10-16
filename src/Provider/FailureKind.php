<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

enum FailureKind: string
{
    case TRANSPORT = 'transport';
    case RATE_LIMIT = 'rate_limit';
    case AUTHENTICATION = 'authentication';
    case INVALID_REQUEST = 'invalid_request';
    case UNAVAILABLE = 'unavailable';
    case INVALID_RESPONSE = 'invalid_response';
    case UNKNOWN = 'unknown';
}
