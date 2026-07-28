<?php

namespace App\Enums;

/**
 * Driver names for App\Services\AI\AiProviderManager, matching the keys
 * under config('ai.providers').
 */
enum AiProviderName: string
{
    case Claude = 'claude';
    case OpenAi = 'openai';
    case Gemini = 'gemini';
    case AzureOpenAi = 'azure_openai';
    case Ollama = 'ollama';
}
