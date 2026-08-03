<?php

namespace App\Game\Effects;

use App\Game\Services\SpecialCardEffectManager;

/**
 * One configurable special card behaviour. New effects are added by implementing
 * this interface and registering the class against a SpecialCardEffect case in
 * {@see SpecialCardEffectManager}.
 */
interface SpecialCardEffectHandler
{
    public function apply(EffectContext $context): void;
}
