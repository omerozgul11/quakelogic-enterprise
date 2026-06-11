<?php

namespace App\Services\Ai;

class AiProviderFactory
{
    public static function make(?string $provider = null): AiProviderInterface
    {
        $provider ??= config('ai.default', 'fake');

        $instance = match ($provider) {
            'openai' => new OpenAiProvider(),
            'anthropic' => new AnthropicProvider(),
            'local' => new LocalLlmProvider(),
            default => new FakeAiProvider(),
        };

        // A live provider selected without credentials (e.g. AI_PROVIDER=anthropic
        // before the API key is set) degrades to the deterministic provider rather
        // than failing every AI call. It activates automatically once the key lands.
        if (!$instance->isAvailable()) {
            return new FakeAiProvider();
        }

        return $instance;
    }

    public static function default(): AiProviderInterface
    {
        return static::make();
    }
}
