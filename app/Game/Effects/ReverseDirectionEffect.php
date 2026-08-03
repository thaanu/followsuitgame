<?php

namespace App\Game\Effects;

use App\Game\Support\ActivityMessage;

/**
 * A King turns the table around.
 */
class ReverseDirectionEffect implements SpecialCardEffectHandler
{
    public function apply(EffectContext $context): void
    {
        $context->game->direction = $context->game->direction->reversed();
        $context->message(ActivityMessage::directionReversed($context->game->direction));
    }
}
