<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Psr\Log\LoggerInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Contracts\HowToo\AiCredentialRepository;

final class AiAssistantProviderManager
{
    /** @var array<string, AiProviderAdapter> */
    private array $adapters = [];

    /** @param iterable<AiProviderAdapter> $adapters */
    public function __construct(
        private AiCredentialRepository $credentials,
        iterable $adapters,
        private LoggerInterface $logger,
    ) {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->name()] = $adapter;
        }
    }

    public function generate(AiProviderPrompt $prompt, ?callable $onAttempt = null): AiProviderResult
    {
        $attempts = 0;

        foreach ($this->credentials->orderedAiProviders() as $provider) {
            $name = $provider['name'];
            $adapter = $this->adapters[$name] ?? null;
            if (!$adapter) {
                continue;
            }

            foreach ($this->credentials->availableAiCredentials(
                $name,
                $provider['model'],
                (int) ($provider['timeout_seconds'] ?? 25),
            ) as $credential) {
                ++$attempts;
                if ($onAttempt) {
                    $onAttempt();
                }

                try {
                    $answer = $adapter->generate($credential, $prompt);
                    $this->credentials->markHealthy($credential);
                    $this->logger->info('AI assistant response generated.', ['provider' => $name]);

                    return new AiProviderResult($name, $answer);
                } catch (AiProviderException $exception) {
                    $this->credentials->putOnCooldown(
                        $credential,
                        $exception->cooldownSeconds(),
                        $exception->reason,
                    );
                    $this->logger->warning('AI assistant provider attempt failed.', [
                        'provider' => $name,
                        'reason' => $exception->reason,
                        'status' => $exception->status,
                    ]);
                } catch (\Throwable) {
                    $this->credentials->putOnCooldown($credential, 120, AiProviderException::UNAVAILABLE);
                    $this->logger->warning('AI assistant provider attempt failed unexpectedly.', [
                        'provider' => $name,
                        'reason' => AiProviderException::UNAVAILABLE,
                    ]);
                }
            }
        }

        $this->logger->error('All configured AI assistant providers failed.', ['attempts' => $attempts]);

        throw new DisplayException('The assistant is temporarily unavailable. All configured providers failed or are cooling down.');
    }
}
