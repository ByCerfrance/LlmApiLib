<?php

namespace ByCerfrance\LlmApiLib\Tests\Provider;

use ByCerfrance\LlmApiLib\LlmInterface;
use PHPUnit\Framework\TestCase;

abstract class ProviderTestCase extends TestCase
{
    protected LlmInterface $provider;
    protected int $sleep = 0;

    public function testChat()
    {
        {
            $completion = $this->provider->chat('Coucou, réponds-moi juste "OK" si tout va bien, rien de plus.');
            $this->assertStringContainsString(
                strtolower('OK'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $this->assertGreaterThanOrEqual(27, $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThanOrEqual(2, $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThanOrEqual(29, $this->provider->getUsage()->getTotalTokens());
        }

        {
            $completion = $this->provider->chat($completion->withNewMessage('Re-réponds moi maintenant "KO".'));
            $this->assertStringContainsString(
                strtolower('KO'),
                strtolower($completion->getLastMessage()->getContent()),
            );
            sleep($this->sleep);

            $this->assertGreaterThanOrEqual(71, $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThanOrEqual(5, $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThanOrEqual(76, $this->provider->getUsage()->getTotalTokens());
        }

        {
            $completion = $this->provider->chat($completion->withNewMessage('Re-donnes moi ta première réponse.'));
            $this->assertStringContainsString(
                strtolower('OK'),
                strtolower($completion->getLastMessage()->getContent()),
            );

            $this->assertGreaterThanOrEqual(131, $this->provider->getUsage()->getPromptTokens());
            $this->assertGreaterThanOrEqual(7, $this->provider->getUsage()->getCompletionTokens());
            $this->assertGreaterThanOrEqual(138, $this->provider->getUsage()->getTotalTokens());
        }
    }
}
