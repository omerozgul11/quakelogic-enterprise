<?php

namespace App\Enums;

enum OpportunitySource: string
{
    case Manual = 'manual';
    case SamGov = 'sam_gov';
    case BidPrime = 'bidprime';
    case GovWin = 'govwin';
    case Merx = 'merx';
    case Biddingo = 'biddingo';
    case BcBid = 'bc_bid';
    case Ungm = 'ungm';
    case Unops = 'unops';
    case Iaea = 'iaea';
    case Nato = 'nato';
    case WorldBank = 'world_bank';
    case Adb = 'adb';
    case AfricanDevelopmentBank = 'african_development_bank';
    case CaliforniaEprocure = 'california_eprocure';
    case StatePortal = 'state_portal';
    case Other = 'other';

    public function label(): string
    {
        return match($this) {
            self::Manual => 'Manual Entry',
            self::SamGov => 'SAM',
            self::BidPrime => 'BidPrime',
            self::GovWin => 'GovWin IQ',
            self::Merx => 'MERX',
            self::Biddingo => 'Biddingo',
            self::BcBid => 'BC Bid',
            self::Ungm => 'UNGM',
            self::Unops => 'UNOPS',
            self::Iaea => 'IAEA',
            self::Nato => 'NATO',
            self::WorldBank => 'World Bank',
            self::Adb => 'Asian Development Bank',
            self::AfricanDevelopmentBank => 'African Development Bank',
            self::CaliforniaEprocure => 'California eProcure',
            self::StatePortal => 'State Procurement Portal',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::SamGov => 'blue',
            self::BidPrime => 'purple',
            self::GovWin => 'indigo',
            self::Manual => 'gray',
            default => 'slate',
        };
    }
}
