<?php

namespace App\Services\Ai;

class AiProviderFactory
{
    public static function make(string $provider = null): AiProviderInterface
    {
        $provider ??= config('ai.default', 'fake');

        return match ($provider) {
            'openai' => new OpenAiProvider(),
            'anthropic' => new AnthropicProvider(),
            'local' => new LocalLlmProvider(),
            default => new FakeAiProvider(),
        };
    }

    public static function default(): AiProviderInterface
    {
        return static::make();
    }
}
