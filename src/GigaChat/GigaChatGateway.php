<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\GigaChat;

use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Providers\Provider;

/**
 * The SDK's openai-compatible gateway, with the certificate chain GigaChat
 * is served with: the Russian Ministry of Digital Development's, which the
 * system store does not know. The chain is a file named in the provider's
 * "ca_bundle"; without one the system store is used. The certificate is
 * always verified.
 */
final class GigaChatGateway extends OpenAiCompatibleGateway
{
    /**
     * The "verify" option of a request: the path of the CA bundle, or true.
     *
     * @param  array<string,mixed>  $config
     */
    public static function verify(array $config): string|bool
    {
        $bundle = $config['ca_bundle'] ?? null;

        return is_string($bundle) && $bundle !== '' ? $bundle : true;
    }

    #[\Override]
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        return parent::client($provider, $timeout)->withOptions(['verify' => self::verify($provider->additionalConfiguration())]);
    }
}
