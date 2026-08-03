<?php

namespace App\Game\Data;

use App\Enums\PunishmentSeverity;
use App\Enums\PunishmentTarget;
use Illuminate\Support\Arr;

/**
 * The optional punishment the host attaches to a game.
 */
final readonly class PunishmentConfiguration
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $emoji,
        public ?PunishmentSeverity $severity,
        public PunishmentTarget $target,
        public bool $agreementConfirmed,
    ) {}

    /**
     * @param  array<string, mixed>|null  $punishment
     */
    public static function fromArray(?array $punishment): ?self
    {
        if ($punishment === null || blank(Arr::get($punishment, 'title'))) {
            return null;
        }

        return new self(
            title: (string) Arr::get($punishment, 'title'),
            description: (string) Arr::get($punishment, 'description', ''),
            emoji: Arr::get($punishment, 'emoji') ?: null,
            severity: PunishmentSeverity::tryFrom((string) Arr::get($punishment, 'severity', '')),
            target: PunishmentTarget::tryFrom((string) Arr::get($punishment, 'target', ''))
                ?? PunishmentTarget::MostCardsCollected,
            agreementConfirmed: (bool) Arr::get($punishment, 'agreement_confirmed', false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'emoji' => $this->emoji,
            'severity' => $this->severity?->value,
            'target' => $this->target->value,
            'agreement_confirmed' => $this->agreementConfirmed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'emoji' => $this->emoji,
            'severity' => $this->severity?->value,
            'severity_label' => $this->severity?->label(),
            'target' => $this->target->value,
            'target_label' => $this->target->label(),
        ];
    }
}
