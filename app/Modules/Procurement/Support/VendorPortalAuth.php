<?php

namespace App\Modules\Procurement\Support;

use App\Modules\Procurement\Models\SupplierContact;

/**
 * Minimal, self-contained session auth for the vendor portal — deliberately
 * separate from the staff `web` guard so a vendor session can never resolve to
 * a staff user or reach any staff route. Stores only the signed-in supplier
 * contact's id in the session.
 */
class VendorPortalAuth
{
    private const KEY = 'vendor_portal_contact_id';

    public static function login(SupplierContact $contact): void
    {
        // Regenerate the id to prevent session fixation, then record the contact.
        session()->regenerate();
        session()->put(self::KEY, $contact->id);
    }

    public static function logout(): void
    {
        session()->forget(self::KEY);
        session()->regenerate();
    }

    public static function id(): ?int
    {
        $id = session()->get(self::KEY);

        return $id ? (int) $id : null;
    }

    /** The signed-in contact (with its supplier), or null. */
    public static function contact(): ?SupplierContact
    {
        $id = self::id();
        if (! $id) {
            return null;
        }

        return SupplierContact::with('supplier')->find($id);
    }
}
