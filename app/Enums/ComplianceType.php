<?php

namespace App\Enums;

/** Phase 7 — categories of company/org compliance documents we keep current. */
enum ComplianceType: string
{
    case W9 = 'w9';
    case Insurance = 'insurance';
    case IsoCertification = 'iso_certification';
    case SamRegistration = 'sam_registration';
    case CageCode = 'cage_code';
    case Uei = 'uei';
    case Nda = 'nda';
    case VendorRegistration = 'vendor_registration';
    case CaliforniaSmallBusiness = 'california_small_business';
    case Cdtfa = 'cdtfa';
    case Ein = 'ein';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::W9 => 'W-9',
            self::Insurance => 'Insurance',
            self::IsoCertification => 'ISO Certification',
            self::SamRegistration => 'SAM Registration',
            self::CageCode => 'CAGE Code',
            self::Uei => 'UEI',
            self::Nda => 'NDA',
            self::VendorRegistration => 'Vendor Registration',
            self::CaliforniaSmallBusiness => 'California Small Business Registration',
            self::Cdtfa => 'CDTFA (CA Dept. of Tax & Fee Administration)',
            self::Ein => 'EIN',
            self::Other => 'Other',
        };
    }

    /** Whether this type typically carries an expiry/renewal date. */
    public function tracksExpiry(): bool
    {
        return match ($this) {
            self::CageCode, self::Uei, self::Ein, self::Nda, self::Other => false,
            default => true,
        };
    }
}
