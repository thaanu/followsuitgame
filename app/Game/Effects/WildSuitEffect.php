<?php

namespace App\Game\Effects;

use App\Game\Services\RuleEngine;
use App\Game\Support\ActivityMessage;

/**
 * A Joker stands in for any suit, so the player nominates the active suit.
 */
class WildSuitEffect implements SpecialCardEffectHandler
{
    public function __construct(private readonly RuleEngine $rules) {}

    public function apply(EffectContext $context): void
    {
        $suit = $this->rules->fallbackSuit($context->card, $context->chosenSuit);

        $context->game->active_suit = $suit;
        $context->message(ActivityMessage::suitChanged($context->player, $suit));
    }
}
