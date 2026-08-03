<?php

namespace App\Events;

use App\Game\Services\GameStatePresenter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for everything broadcast to the whole table.
 *
 * The state array is built by {@see GameStatePresenter} before the
 * event is dispatched, so no hidden information can reach the payload and the queued
 * broadcast does not have to re-query the database.
 */
abstract class PublicGameEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $gamePublicId,
        public array $state,
        public array $payload = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("game.{$this->gamePublicId}")];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'state' => $this->state,
            'payload' => $this->payload,
        ];
    }
}
