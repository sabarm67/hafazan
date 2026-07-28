<?php

namespace App\Services\AI;

use App\Services\AI\Providers\AzureOpenAiProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Support\Manager;

/**
 * Resolves the configured AiProviderInterface driver. Uses Laravel's own
 * driver-manager pattern (the same one powering cache/queue/mail) so adding a
 * new provider later only means adding a create*Driver() method + config
 * block, with zero changes to any consuming code.
 */
class AiProviderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('ai.default', 'claude');
    }

    public function createClaudeDriver(): ClaudeProvider
    {
        return new ClaudeProvider($this->config->get('ai.providers.claude', []));
    }

    public function createOpenaiDriver(): OpenAiProvider
    {
        return new OpenAiProvider($this->config->get('ai.providers.openai', []));
    }

    public function createGeminiDriver(): GeminiProvider
    {
        return new GeminiProvider($this->config->get('ai.providers.gemini', []));
    }

    public function createAzureOpenaiDriver(): AzureOpenAiProvider
    {
        return new AzureOpenAiProvider($this->config->get('ai.providers.azure_openai', []));
    }

    public function createOllamaDriver(): OllamaProvider
    {
        return new OllamaProvider($this->config->get('ai.providers.ollama', []));
    }
}
