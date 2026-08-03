<?php

namespace App\Enums;

enum ConnectionStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
}
