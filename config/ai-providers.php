<?php

// Presets use native APIs for Gemini, Anthropic and Azure; all other entries
// implement the OpenAI chat-completions protocol. Models remain editable.
return [
    'openai' => ['name' => 'OpenAI', 'url' => 'https://api.openai.com/v1', 'model' => 'gpt-4o-mini'],
    'gemini' => ['name' => 'Google Gemini', 'url' => 'https://generativelanguage.googleapis.com', 'model' => 'gemini-2.5-flash'],
    'anthropic' => ['name' => 'Anthropic Claude', 'url' => 'https://api.anthropic.com', 'model' => 'claude-sonnet-4-5'],
    'azure_openai' => ['name' => 'Azure OpenAI', 'url' => '', 'model' => '', 'hint' => 'Enter your Azure resource URL and deployment name as the model.'],
    'groq' => ['name' => 'Groq', 'url' => 'https://api.groq.com/openai/v1', 'model' => 'llama-3.3-70b-versatile'],
    'mistral' => ['name' => 'Mistral', 'url' => 'https://api.mistral.ai/v1', 'model' => 'mistral-small-latest'],
    'deepseek' => ['name' => 'DeepSeek', 'url' => 'https://api.deepseek.com/v1', 'model' => 'deepseek-chat'],
    'openrouter' => ['name' => 'OpenRouter', 'url' => 'https://openrouter.ai/api/v1', 'model' => 'openai/gpt-4o-mini'],
    'xai' => ['name' => 'xAI Grok', 'url' => 'https://api.x.ai/v1', 'model' => 'grok-3-mini'],
    'together' => ['name' => 'Together AI', 'url' => 'https://api.together.xyz/v1', 'model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo'],
    'fireworks' => ['name' => 'Fireworks AI', 'url' => 'https://api.fireworks.ai/inference/v1', 'model' => 'accounts/fireworks/models/llama-v3p3-70b-instruct'],
    'perplexity' => ['name' => 'Perplexity', 'url' => 'https://api.perplexity.ai', 'model' => 'sonar', 'endpoint' => '/chat/completions', 'json_mode' => false],
    'cohere' => ['name' => 'Cohere', 'url' => 'https://api.cohere.ai/compatibility/v1', 'model' => 'command-a-03-2025'],
    'huggingface' => ['name' => 'Hugging Face', 'url' => 'https://router.huggingface.co/v1', 'model' => '', 'hint' => 'Select a model available to your Hugging Face inference account.'],
    'nvidia' => ['name' => 'NVIDIA NIM', 'url' => 'https://integrate.api.nvidia.com/v1', 'model' => 'meta/llama-3.3-70b-instruct'],
    'cerebras' => ['name' => 'Cerebras', 'url' => 'https://api.cerebras.ai/v1', 'model' => 'llama3.1-8b'],
    'sambanova' => ['name' => 'SambaNova', 'url' => 'https://api.sambanova.ai/v1', 'model' => 'Meta-Llama-3.3-70B-Instruct'],
    'ollama' => ['name' => 'Ollama', 'url' => 'http://localhost:11434/v1', 'model' => 'llama3.2'],
    'openai_compatible' => ['name' => 'Other OpenAI-compatible API', 'url' => '', 'model' => ''],
    'custom' => ['name' => 'Custom OpenAI-compatible REST API', 'url' => '', 'model' => ''],
];
