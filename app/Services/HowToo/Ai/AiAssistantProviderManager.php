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
        private int $totalTimeoutSeconds = 90,
    ) {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->name()] = $adapter;
        }
    }

    public function generate(AiProviderPrompt $prompt, ?callable $onAttempt = null): AiProviderResult
    {
        return $this->execute($prompt, false, null, $onAttempt, null);
    }

    public function stream(
        AiProviderPrompt $prompt,
        callable $onDelta,
        ?callable $onAttempt = null,
        ?callable $onReset = null,
    ): AiProviderResult {
        return $this->execute($prompt, true, $onDelta, $onAttempt, $onReset);
    }

    private function execute(
        AiProviderPrompt $prompt,
        bool $stream,
        ?callable $onDelta,
        ?callable $onAttempt,
        ?callable $onReset,
    ): AiProviderResult {
        $attempts = 0;
        $deadline = microtime(true) + $this->totalTimeoutSeconds();

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
                $remaining = (int) floor($deadline - microtime(true));
                if ($remaining <= 0) {
                    break 2;
                }

                ++$attempts;
                if ($onAttempt) {
                    $onAttempt();
                }

                $credential = $credential->withTimeout(min($credential->timeoutSeconds, $remaining));
                $emitted = false;
                try {
                    if ($stream && $adapter instanceof StreamingAiProviderAdapter) {
                        $answer = $adapter->stream(
                            $credential,
                            $prompt,
                            function (string $delta) use (&$emitted, $onDelta): void {
                                $emitted = true;
                                if ($onDelta) {
                                    $onDelta($delta);
                                }
                            },
                        );
                    } else {
                        $answer = $adapter->generate($credential, $prompt);
                        if ($stream) {
                            $emitted = true;
                            if ($onDelta) {
                                $onDelta($answer);
                            }
                        }
                    }
                    $this->credentials->markHealthy($credential);
                    $this->logger->info('AI assistant response generated.', ['provider' => $name]);

                    return new AiProviderResult($name, $answer);
                } catch (AiStreamCancelledException $exception) {
                    throw $exception;
                } catch (AiProviderException $exception) {
                    if ($emitted) {
                        $onReset?->__invoke();
                    }
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
                    if ($emitted) {
                        $onReset?->__invoke();
                    }
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

    private function totalTimeoutSeconds(): int
    {
        return max(60, min($this->totalTimeoutSeconds, 120));
    }
}
