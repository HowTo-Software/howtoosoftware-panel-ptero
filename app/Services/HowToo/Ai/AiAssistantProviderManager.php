<?php

namespace Pterodactyl\Services\HowToo\Ai;

use Psr\Log\LoggerInterface;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Contracts\HowToo\AiCredentialRepository;

final class AiAssistantProviderManager
{
    /** @var array<string, AiProviderAdapter> */
    private array $adapters = [];
    private AiPromptBudgeter $budgeter;

    /** @var \Closure(int): void */
    private \Closure $sleep;

    /** @param iterable<AiProviderAdapter> $adapters */
    public function __construct(
        private AiCredentialRepository $credentials,
        iterable $adapters,
        private LoggerInterface $logger,
        private int $totalTimeoutSeconds = 90,
        ?AiPromptBudgeter $budgeter = null,
        ?\Closure $sleep = null,
    ) {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->name()] = $adapter;
        }

        $this->budgeter = $budgeter ?? new AiPromptBudgeter();
        $this->sleep = $sleep ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
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
        $failures = [];
        $deadline = microtime(true) + $this->totalTimeoutSeconds();
        $prompt = $this->budgeter->compact($prompt);
        $queue = $this->attemptQueue();

        foreach ($queue as $index => $attempt) {
            $remaining = (int) floor($deadline - microtime(true));
            if ($remaining < 5) {
                break;
            }

            $name = $attempt['provider'];
            $adapter = $attempt['adapter'];
            $credential = $attempt['credential']->withTimeout($this->attemptTimeout(
                $attempt['credential'],
                $prompt,
                $remaining,
                count($queue) - $index,
            ));
            $retry = 0;

            while (true) {
                ++$attempts;
                if ($onAttempt) {
                    $onAttempt();
                }

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

                    $retryDelay = $retry === 0 && !$emitted ? $this->retryDelayMilliseconds($exception) : null;
                    $remaining = (int) floor($deadline - microtime(true));
                    if ($retryDelay !== null && $remaining > (int) ceil($retryDelay / 1000) + 5) {
                        ++$retry;
                        $this->logger->info('Retrying a rate-limited AI assistant provider.', [
                            'provider' => $name,
                            'reason' => $exception->reason,
                        ]);
                        ($this->sleep)($retryDelay);
                        continue;
                    }

                    $failures[] = $exception->reason;
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
                    break;
                } catch (\Throwable) {
                    if ($emitted) {
                        $onReset?->__invoke();
                    }
                    $failures[] = AiProviderException::UNAVAILABLE;
                    $this->credentials->putOnCooldown($credential, 120, AiProviderException::UNAVAILABLE);
                    $this->logger->warning('AI assistant provider attempt failed unexpectedly.', [
                        'provider' => $name,
                        'reason' => AiProviderException::UNAVAILABLE,
                    ]);
                    break;
                }
            }
        }

        $this->logger->error('All configured AI assistant providers failed.', ['attempts' => $attempts]);

        if ($failures !== [] && count(array_unique($failures)) === 1 && $failures[0] === AiProviderException::RATE_LIMIT) {
            throw new DisplayException('The assistant is at capacity right now. Please wait a moment and try again.');
        }

        if ($failures !== [] && count(array_unique($failures)) === 1 && $failures[0] === AiProviderException::TIMEOUT) {
            throw new DisplayException('The assistant took too long to complete the answer. Please try again with the same question.');
        }

        throw new DisplayException('The assistant is temporarily unavailable. All configured providers failed or are cooling down.');
    }

    private function attemptQueue(): array
    {
        $queue = [];

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
                $queue[] = [
                    'provider' => $name,
                    'adapter' => $adapter,
                    'credential' => $credential,
                ];
            }
        }

        return $queue;
    }

    private function attemptTimeout(
        AiProviderCredential $credential,
        AiProviderPrompt $prompt,
        int $remainingSeconds,
        int $remainingAttempts,
    ): int {
        $promptLength = mb_strlen($prompt->system)
            + collect($prompt->messages)->sum(fn (array $message): int => mb_strlen((string) ($message['content'] ?? '')));
        $complexityBoost = match (true) {
            $promptLength >= 15000 => 15,
            $promptLength >= 9000 => 10,
            default => 0,
        };
        $desired = min(55, $credential->timeoutSeconds + $complexityBoost);
        $fairShare = max(8, (int) floor($remainingSeconds / max(1, $remainingAttempts)));

        return max(5, min($desired, $fairShare, $remainingSeconds));
    }

    private function retryDelayMilliseconds(AiProviderException $exception): ?int
    {
        if ($exception->reason !== AiProviderException::RATE_LIMIT
            || $exception->retryAfter === null
            || $exception->retryAfter > 2) {
            return null;
        }

        return max(250, $exception->retryAfter * 1000) + random_int(50, 250);
    }

    private function totalTimeoutSeconds(): int
    {
        return max(60, min($this->totalTimeoutSeconds, 120));
    }
}
