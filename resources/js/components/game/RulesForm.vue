<script setup lang="ts">
import { computed } from 'vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectField from '@/components/ui/SelectField.vue';
import ToggleField from '@/components/ui/ToggleField.vue';
import type { RuleOptions, RuleSet } from '@/types/game';

defineProps<{ options: RuleOptions; errors?: Record<string, string> }>();

const rules = defineModel<RuleSet>({ required: true });

const showFixedCount = computed(
    () => rules.value.general.deal_method === 'fixed_count',
);
const showStartingCard = computed(
    () => rules.value.general.starting_player_method === 'specific_card',
);
const showDrawCount = computed(
    () => rules.value.follow_suit.unable_to_follow_action === 'draw_cards',
);
const showPileCount = computed(
    () => rules.value.pile.end_condition === 'fixed_card_count',
);
const showRoundLimit = computed(
    () => rules.value.winning.condition === 'fixed_rounds',
);
const showRoundWinner = computed(() =>
    ['every_player_played', 'fixed_card_count'].includes(
        rules.value.pile.end_condition,
    ),
);
const showClearPileRank = computed(
    () =>
        rules.value.special_cards.enabled &&
        rules.value.special_cards.effects.clear_pile,
);
</script>

<template>
    <div class="space-y-4">
        <AppPanel
            title="General"
            subtitle="The deck, the deal, and who goes first."
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <SelectField
                    id="deck-type"
                    v-model="rules.general.deck_type"
                    label="Deck"
                    :options="options.deck_types"
                    :error="errors?.['rules.general.deck_type']"
                />

                <SelectField
                    id="deal-method"
                    v-model="rules.general.deal_method"
                    label="Dealing"
                    :options="options.deal_methods"
                    :error="errors?.['rules.general.deal_method']"
                />

                <FormField
                    v-if="showFixedCount"
                    label="Cards per player"
                    for="fixed-cards"
                    :error="errors?.['rules.general.fixed_cards_per_player']"
                >
                    <input
                        id="fixed-cards"
                        v-model.number="rules.general.fixed_cards_per_player"
                        type="number"
                        min="1"
                        max="13"
                        class="field-control"
                    />
                </FormField>

                <SelectField
                    id="starting-player"
                    v-model="rules.general.starting_player_method"
                    label="Starting player"
                    :options="options.starting_player_methods"
                    :error="errors?.['rules.general.starting_player_method']"
                />

                <SelectField
                    v-if="showStartingCard"
                    id="starting-card"
                    v-model="rules.general.starting_card_code"
                    label="Card that decides who starts"
                    :options="
                        options.cards.map((card) => ({
                            value: card.code,
                            label: card.label,
                        }))
                    "
                    :error="errors?.['rules.general.starting_card_code']"
                />

                <SelectField
                    id="turn-direction"
                    v-model="rules.general.turn_direction"
                    label="Turn direction"
                    :options="options.turn_directions"
                    :error="errors?.['rules.general.turn_direction']"
                />
            </div>
        </AppPanel>

        <AppPanel
            title="Following suit"
            subtitle="What happens when a player is stuck."
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <SelectField
                    id="unable-action"
                    v-model="rules.follow_suit.unable_to_follow_action"
                    label="When a player cannot follow suit"
                    :options="options.unable_to_follow_actions"
                    :error="
                        errors?.['rules.follow_suit.unable_to_follow_action']
                    "
                />

                <FormField
                    v-if="showDrawCount"
                    label="Cards to draw"
                    for="draw-count"
                    :error="errors?.['rules.follow_suit.draw_card_count']"
                >
                    <input
                        id="draw-count"
                        v-model.number="rules.follow_suit.draw_card_count"
                        type="number"
                        min="1"
                        max="5"
                        class="field-control"
                    />
                </FormField>

                <SelectField
                    id="next-lead"
                    v-model="rules.follow_suit.next_lead_rule"
                    label="Who leads the next pile"
                    :options="options.next_lead_rules"
                    :error="errors?.['rules.follow_suit.next_lead_rule']"
                />

                <div class="sm:col-span-2">
                    <ToggleField
                        id="voluntary-collect"
                        v-model="rules.follow_suit.allow_voluntary_collect"
                        label="Allow players to collect the pile on purpose"
                        description="A player may take the pile even when they could follow suit."
                    />
                </div>
            </div>
        </AppPanel>

        <AppPanel
            title="The pile"
            subtitle="When a pile ends and where its cards go."
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <SelectField
                    id="pile-end"
                    v-model="rules.pile.end_condition"
                    label="A pile ends"
                    :options="options.pile_end_conditions"
                    :error="errors?.['rules.pile.end_condition']"
                />

                <FormField
                    v-if="showPileCount"
                    label="Cards in a pile"
                    for="pile-count"
                    :error="errors?.['rules.pile.fixed_card_count']"
                >
                    <input
                        id="pile-count"
                        v-model.number="rules.pile.fixed_card_count"
                        type="number"
                        min="2"
                        max="20"
                        class="field-control"
                    />
                </FormField>

                <SelectField
                    id="collected-destination"
                    v-model="rules.pile.collected_cards_destination"
                    label="Collected cards"
                    :options="options.collected_cards_destinations"
                    :error="errors?.['rules.pile.collected_cards_destination']"
                />
            </div>
        </AppPanel>

        <AppPanel
            title="Card ranking"
            subtitle="How cards compare when a pile is judged."
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <SelectField
                    id="ace-rule"
                    v-model="rules.ranking.ace_rule"
                    label="Ace value"
                    :options="options.ace_rules"
                    :error="errors?.['rules.ranking.ace_rule']"
                />

                <SelectField
                    v-if="showRoundWinner"
                    id="round-winner"
                    v-model="rules.ranking.round_winner"
                    label="A completed pile goes to"
                    :options="options.round_winners"
                    :error="errors?.['rules.ranking.round_winner']"
                />
            </div>
        </AppPanel>

        <AppPanel
            title="Special cards"
            subtitle="Optional extra powers. Off by default."
        >
            <ToggleField
                id="special-enabled"
                v-model="rules.special_cards.enabled"
                label="Use special card effects"
                description="Turn individual effects on below."
            />

            <div
                v-if="rules.special_cards.enabled"
                class="mt-4 grid gap-3 sm:grid-cols-2"
            >
                <ToggleField
                    v-for="effect in options.special_card_effects"
                    :id="`effect-${effect.value}`"
                    :key="effect.value"
                    v-model="rules.special_cards.effects[effect.value]"
                    :label="effect.label"
                />

                <div v-if="showClearPileRank" class="sm:col-span-2">
                    <SelectField
                        id="clear-pile-rank"
                        v-model="rules.special_cards.clear_pile_rank as string"
                        label="Rank that clears the pile"
                        :options="options.clear_pile_ranks"
                        :error="errors?.['rules.special_cards.clear_pile_rank']"
                    />
                </div>
            </div>
        </AppPanel>

        <AppPanel
            title="Winning"
            subtitle="How the game ends and how ties are settled."
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <SelectField
                    id="win-condition"
                    v-model="rules.winning.condition"
                    label="Winning condition"
                    :options="options.winning_conditions"
                    :error="errors?.['rules.winning.condition']"
                />

                <FormField
                    v-if="showRoundLimit"
                    label="Number of rounds"
                    for="round-limit"
                    :error="errors?.['rules.winning.round_limit']"
                >
                    <input
                        id="round-limit"
                        v-model.number="rules.winning.round_limit"
                        type="number"
                        min="1"
                        max="10"
                        class="field-control"
                    />
                </FormField>

                <SelectField
                    id="tie-break"
                    v-model="rules.winning.tie_break"
                    label="Tie break"
                    :options="options.tie_break_rules"
                    :error="errors?.['rules.winning.tie_break']"
                />
            </div>
        </AppPanel>

        <AppPanel
            title="Turn timer"
            subtitle="Optional. The server keeps the clock."
        >
            <ToggleField
                id="timer-enabled"
                v-model="rules.timer.enabled"
                label="Use a turn timer"
            />

            <div
                v-if="rules.timer.enabled"
                class="mt-4 grid gap-4 sm:grid-cols-2"
            >
                <FormField
                    label="Seconds per turn"
                    for="timer-seconds"
                    :error="errors?.['rules.timer.seconds']"
                >
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in options.timer_presets"
                            :key="preset"
                            type="button"
                            :class="[
                                'rounded-lg px-3 py-2 text-sm font-medium transition',
                                rules.timer.seconds === preset
                                    ? 'bg-brass-400 text-felt-950'
                                    : 'bg-white/10 text-white hover:bg-white/20',
                            ]"
                            @click="rules.timer.seconds = preset"
                        >
                            {{ preset }}s
                        </button>
                        <input
                            id="timer-seconds"
                            v-model.number="rules.timer.seconds"
                            type="number"
                            min="5"
                            max="600"
                            class="field-control w-24"
                            aria-label="Custom turn length in seconds"
                        />
                    </div>
                </FormField>

                <SelectField
                    id="timeout-action"
                    v-model="rules.timer.timeout_action"
                    label="When the time runs out"
                    :options="options.timeout_actions"
                    :error="errors?.['rules.timer.timeout_action']"
                />
            </div>
        </AppPanel>

        <AppPanel
            title="Connection"
            subtitle="What happens when somebody drops out."
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Reconnection grace period (seconds)"
                    for="grace"
                    hint="How long a player has to come back before the rule below applies."
                    :error="errors?.['rules.connection.grace_period_seconds']"
                >
                    <input
                        id="grace"
                        v-model.number="rules.connection.grace_period_seconds"
                        type="number"
                        min="10"
                        max="600"
                        class="field-control"
                    />
                </FormField>

                <SelectField
                    id="disconnect-behaviour"
                    v-model="rules.connection.disconnect_behaviour"
                    label="If a player stays away"
                    :options="options.disconnect_behaviours"
                    :error="errors?.['rules.connection.disconnect_behaviour']"
                />
            </div>
        </AppPanel>
    </div>
</template>
