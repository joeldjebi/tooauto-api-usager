<?php

namespace App\Providers;

use App\Services\Assistant\AnthropicClient;
use App\Services\Assistant\Contracts\LlmClient;
use App\Services\Assistant\OllamaClient;
use App\Services\Assistant\ToolRegistry;
use App\Services\Assistant\Tools\SearchCampaignsTool;
use App\Services\Assistant\Tools\SearchEstablishmentsTool;
use Illuminate\Support\ServiceProvider;

class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app) {
            $registry = new ToolRegistry();

            $registry->register($app->make(SearchCampaignsTool::class));
            $registry->register($app->make(SearchEstablishmentsTool::class));

            return $registry;
        });

        $this->app->bind(LlmClient::class, function ($app) {
            return match (config('services.assistant.provider')) {
                'ollama' => $app->make(OllamaClient::class),
                default => $app->make(AnthropicClient::class),
            };
        });
    }
}
