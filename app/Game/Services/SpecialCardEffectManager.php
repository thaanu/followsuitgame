<?php

namespace App\Game\Services;

use App\Enums\CardSuit;
use App\Enums\SpecialCardEffect;
use App\Game\Effects\ChangeSuitEffect;
use App\Game\Effects\ClearPileEffect;
use App\Game\Effects\EffectContext;
use App\Game\Effects\ExtraTurnEffect;
use App\Game\Effects\ReverseDirectionEffect;
use App\Game\Effects\SkipNextPlayerEffect;
use App\Game\Effects\SpecialCardEffectHandler;
use App\Game\Effects\WildSuitEffect;
use App\Models\Game;
use App\Models\GameCard;
use App\Models\GamePlayer;
use Illuminate\Contracts\Container\Container;

/**
 * Maps each special card effect to the class that performs it.
 *
 * Adding a new effect is a matter of adding a case to {@see SpecialCardEffect},
 * writing a handler, and registering it here.
 */
class SpecialCardEffectManager
{
    /** @var array<string, class-string<SpecialCardEffectHandler>> */
    private array $handlers = [
        SpecialCardEffect::AceChangesSuit->value => ChangeSuitEffect::class,
        SpecialCardEffect::KingReversesDirection->value => ReverseDirectionEffect::class,
        SpecialCardEffect::QueenSkipsNext->value => SkipNextPlayerEffect::class,
        SpecialCardEffect::JackExtraTurn->value => ExtraTurnEffect::class,
        SpecialCardEffect::JokerWildSuit->value => WildSuitEffect::class,
        SpecialCardEffect::ClearPile->value => ClearPileEffect::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly RuleEngine $rules,
    ) {}

    /**
     * Run every effect triggered by this card and report what they asked for.
     */
    public function apply(Game $game, GamePlayer $player, GameCard $card, ?CardSuit $chosenSuit): EffectContext
    {
        $context = new EffectContext($game, $player, $card, $chosenSuit);

        foreach ($this->rules->effectsFor($game, $card) as $effect) {
            $this->handlerFor($effect)?->apply($context);
        }

        return $context;
    }

    public function handlerFor(SpecialCardEffect $effect): ?SpecialCardEffectHandler
    {
        $class = $this->handlers[$effect->value] ?? null;

        if ($class === null) {
            return null;
        }

        return $this->container->make($class);
    }

    /**
     * Register a handler for an effect, overriding the default binding.
     *
     * @param  class-string<SpecialCardEffectHandler>  $handler
     */
    public function register(SpecialCardEffect $effect, string $handler): void
    {
        $this->handlers[$effect->value] = $handler;
    }
}
