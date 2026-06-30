<?php

namespace App\Services\Mailings;

use App\Enums\Carrier;
use App\Models\Organization;

/**
 * The org's custom shipping carriers (e.g. freight companies like J.B. Hunt) that
 * aren't one of the built-in {@see Carrier} cases. There is no carriers table —
 * the names live in the Organization.settings JSON blob under `custom_carriers`,
 * mirroring ProposalStyleService. The mailing `carrier` column stores the name
 * verbatim; only UPS is tracked live, the rest are tracked manually.
 */
class CarrierRegistry
{
    private const KEY = 'custom_carriers';

    private const HIDDEN_KEY = 'hidden_carriers';

    private const MAX_LEN = 50;

    /** @return list<string> the saved custom carrier names */
    public function names(Organization $org): array
    {
        $stored = is_array($org->settings[self::KEY] ?? null) ? $org->settings[self::KEY] : [];

        return collect($stored)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n))
            ->values()
            ->all();
    }

    /**
     * Add a custom carrier name. Returns the stored name, or null if it was blank
     * or already a built-in carrier (those are always available, nothing to add).
     */
    public function add(Organization $org, string $name): ?string
    {
        $name = mb_substr(trim($name), 0, self::MAX_LEN);
        if ($name === '' || $this->isBuiltIn($name)) {
            return null;
        }

        $names = $this->names($org);
        foreach ($names as $existing) {
            if (strcasecmp($existing, $name) === 0) {
                return $existing; // already registered
            }
        }
        $names[] = $name;
        $this->persist($org, $names);

        return $name;
    }

    public function remove(Organization $org, string $name): void
    {
        $kept = array_values(array_filter(
            $this->names($org),
            fn ($n) => strcasecmp($n, $name) !== 0,
        ));
        $this->persist($org, $kept);
    }

    /**
     * Rename a custom carrier. Returns the stored new name, or null if it's blank
     * or a built-in carrier. If the new name already matches another custom
     * carrier, the two are merged (the old name is simply dropped). Callers are
     * responsible for re-pointing shipments from the old name to the returned one.
     */
    public function rename(Organization $org, string $from, string $to): ?string
    {
        $to = mb_substr(trim($to), 0, self::MAX_LEN);
        if ($to === '' || $this->isBuiltIn($to)) {
            return null;
        }

        $kept = array_values(array_filter(
            $this->names($org),
            fn ($n) => strcasecmp($n, $from) !== 0,
        ));

        $alreadyThere = false;
        foreach ($kept as $n) {
            if (strcasecmp($n, $to) === 0) {
                $alreadyThere = true;
                break;
            }
        }
        if (! $alreadyThere) {
            $kept[] = $to;
        }

        $this->persist($org, $kept);

        return $to;
    }

    public function isBuiltIn(string $name): bool
    {
        foreach (Carrier::cases() as $c) {
            if (strcasecmp($c->value, $name) === 0 || strcasecmp($c->label(), $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $names */
    private function persist(Organization $org, array $names): void
    {
        $settings = is_array($org->settings) ? $org->settings : [];
        $settings[self::KEY] = array_values($names);
        $org->update(['settings' => $settings]);
    }

    // --- Hidden built-in carriers -------------------------------------------
    // Built-in carriers are code-defined and can't be deleted, but an org can
    // "remove" one it never uses — it's hidden from the Carriers page and the
    // shipment carrier pickers until restored. Stored as normalized enum values.

    /** @return list<string> the org's hidden built-in carrier values */
    public function hidden(Organization $org): array
    {
        $stored = is_array($org->settings[self::HIDDEN_KEY] ?? null) ? $org->settings[self::HIDDEN_KEY] : [];

        return collect($stored)
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isHidden(Organization $org, string $key): bool
    {
        return in_array(mb_strtolower(trim($key)), $this->hidden($org), true);
    }

    public function hide(Organization $org, string $key): void
    {
        $key = mb_strtolower(trim($key));
        if ($key === '') {
            return;
        }
        $hidden = $this->hidden($org);
        if (! in_array($key, $hidden, true)) {
            $hidden[] = $key;
            $this->persistHidden($org, $hidden);
        }
    }

    public function unhide(Organization $org, string $key): void
    {
        $key = mb_strtolower(trim($key));
        $kept = array_values(array_filter($this->hidden($org), fn ($k) => $k !== $key));
        $this->persistHidden($org, $kept);
    }

    /** @param list<string> $hidden */
    private function persistHidden(Organization $org, array $hidden): void
    {
        $settings = is_array($org->settings) ? $org->settings : [];
        $settings[self::HIDDEN_KEY] = array_values($hidden);
        $org->update(['settings' => $settings]);
    }
}
