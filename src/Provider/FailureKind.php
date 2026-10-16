<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

enum FailureKind: string
{
    case TRANSPORT = 'transport';
    case RATE_LIMIT = 'rate_limit';
    case AUTHENTICATION = 'authentication';
    case INVALID_REQUEST = 'invalid_request';
    /** The provider refused the prompt or the generated content (safety, policy or recitation). */
    case CONTENT_REFUSED = 'content_refused';
    case UNAVAILABLE = 'unavailable';
    case INVALID_RESPONSE = 'invalid_response';
    case UNKNOWN = 'unknown';
}
