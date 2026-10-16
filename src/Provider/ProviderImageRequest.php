<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Provider;

use ByCerfrance\LlmApiLib\Image\ImageRequest;
use ByCerfrance\LlmApiLib\Image\ImageRequestInterface;
use ByCerfrance\LlmApiLib\Model\ModelInterface;

readonly class ProviderImageRequest
{
    /** @throws \InvalidArgumentException When the request breaks the ImageRequest invariants, whatever its implementation. */
    public function __construct(public ModelInterface $model, public ImageRequestInterface $request)
    {
        new ImageRequest($request->getPrompt(), $request->getCount(), $request->getOptions(), $request->getAspectRatio());
    }
}
