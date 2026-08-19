<?php

declare(strict_types=1);

namespace Tradernet\Sdk\Config;

/**
 * How the SDK obtains and attaches SID.
 *
 * - keys_only: HMAC only; SID never obtained or attached.
 * - sid_lazy: SID obtained only for commands with requiresSid=true (default).
 * - sid_eager: SID obtained at client construction; still attached only when needed.
 */
enum AuthMode: string
{
    /** HMAC API keys only — no login/password, no SID cookie. */
    case KEYS_ONLY = 'keys_only';

    /** Obtain SID during Tradernet construction. */
    case SID_EAGER = 'sid_eager';

    /** Obtain SID on demand for SID-required commands. */
    case SID_LAZY = 'sid_lazy';
}
