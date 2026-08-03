<?php

namespace App\Game\Effects;

/**
 * A configured rank sweeps the pile off the table entirely; nobody collects it.
 */
class ClearPileEffect implements SpecialCardEffectHandler
{
    public function apply(EffectContext $context): void
    {
        $context->clearsPile = true;
    }
}
