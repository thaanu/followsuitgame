<?php

namespace App\Game\Effects;

/**
 * A Queen makes the turn jump over the next seat.
 */
class SkipNextPlayerEffect implements SpecialCardEffectHandler
{
    public function apply(EffectContext $context): void
    {
        $context->extraSkips++;
    }
}
