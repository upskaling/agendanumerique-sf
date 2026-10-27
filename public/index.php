<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): Kernel {
    $environment = $context['APP_ENV'] ?? 'dev';

    if (!\is_string($environment)) {
        throw new \RuntimeException('The "APP_ENV" runtime option must be a string.');
    }

    return new Kernel($environment, (bool) $context['APP_DEBUG']);
};
