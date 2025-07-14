<?php

namespace Kreatif\StatamicFragmentCache\Support;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;

class StatamicFragmentCacheLogger
{

    protected string $prefix = "[FragmentCache]";


    public function isEnabled(): bool
    {
        return \Kreatif\StatamicFragmentCache\Facades\StatamicFragmentCache::logEnabled();
    }

    protected function getLogLevel(): string {
        return config('statamic.fragment-cache.logging.level', config('logging.default.level', 'warning'));
    }

    protected function getLogChannel(): string {
        return config('statamic.fragment-cache.logging.channel', config('logging.default', 'stack'));
    }


    protected function logger(): ?Logger
    {
        return Log::channel($this->getLogChannel());
    }

    public function log(string $message, array $context = [], string $level = null): void {
        if (!$this->isEnabled()) {
            return;
        }
        if (!$level) {
            $level = $this->getLogLevel();
        }
        $this->logger()->log($level, $this->prefix . $message, $context);
    }

    public function debug(string $message, array $context = []): void {
        $this->log($message, $context, 'debug');
    }

    public function info(string $message, array $context = []): void {
        $this->log($message, $context, 'info');
    }

    public function warning(string $message, array $context = []): void {
        $this->log($message, $context, 'warning');
    }

    public function error(string $message, array $context = []): void {
        $this->log($message, $context, 'error');
    }

    public function critical(string $message, array $context = []): void {
        $this->log($message, $context, 'critical');
    }
}
