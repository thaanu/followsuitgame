<?php

namespace App\Game\Effects;

use App\Game\Support\ActivityMessage;

/**
 * A Jack lets the same player go again.
 */
class ExtraTurnEffect implements SpecialCardEffectHandler
{
    public function apply(EffectContext $context): void
    {
        $context->grantsExtraTurn = true;
        $context->message(ActivityMessage::extraTurn($context->player));
    }
}
