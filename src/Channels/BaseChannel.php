<?php

namespace Kriss\Notification\Channels;

use Closure;
use Kriss\Notification\Factory;
use Kriss\Notification\Services\RateLimiter;
use Throwable;

abstract class BaseChannel
{
    protected array $config = [];
    private ?Factory $factory = null;
    private ?RateLimiter $rateLimiter = null;

    final public function withConfig(array $config): self
    {
        $this->config = array_replace_recursive($this->config, $config);
        return $this;
    }

    final public function withFactory(Factory $factory): self
    {
        $this->factory = $factory;
        return $this;
    }

    final public function withRateLimit(string $key, int $maxAttempts, int $decaySeconds): self
    {
        $this->rateLimiter = $this->factory->getContainer()->make(RateLimiter::class)
            ->withConfig([
                'key' => $key,
                'maxAttempts' => $maxAttempts,
                'decaySeconds' => $decaySeconds,
            ]);
        return $this;
    }

    final protected function wrapSendCallback(Closure $send, $failedResult = false)
    {
        if ($this->rateLimiter && !$this->rateLimiter->attempt()) {
            return $failedResult;
        }

        try {
            return call_user_func($send);
        } catch (Throwable $e) {
            if ($this->factory) {
                $this->factory->handleException($e);
            } else {
                throw $e;
            }
            return $failedResult;
        }
    }
}