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
        $completions = $this->provider->chat('Coucou, réponds-moi juste "OK" si tout va bien, rien de plus.');
        $this->assertStringContainsString('OK', $completions->getLastMessage()->getContent());
        sleep($this->sleep);

        $completions = $this->provider->chat($completions->withNewMessage('Re-réponds moi maintenant "KO".'));
        $this->assertStringContainsString('KO', trim($completions->getLastMessage()->getContent()));
        sleep($this->sleep);

        $completions = $this->provider->chat($completions->withNewMessage('Re-donnes moi ta première réponse.'));
        $this->assertStringContainsString('OK', trim($completions->getLastMessage()->getContent()));
    }
}
