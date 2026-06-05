<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Disconnected = 'disconnected';
    case Connected = 'connected';
    case Error = 'error';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match($this) {
            self::Disconnected => 'Disconnected',
            self::Connected => 'Connected',
            self::Error => 'Error',
            self::Disabled => 'Disabled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Disconnected => 'gray',
            self::Connected => 'green',
            self::Error => 'red',
            self::Disabled => 'slate',
        };
    }
}
