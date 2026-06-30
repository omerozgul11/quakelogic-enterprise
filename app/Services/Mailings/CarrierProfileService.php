<?php

namespace App\Services\Mailings;

use App\Models\Organization;

/**
 * Per-carrier account details an org keeps for internal reference: import/export
 * account numbers (customs) and the carrier's login URL. Applies to BOTH built-in
 * {@see \App\Enums\Carrier} carriers and custom ones. Like {@see CarrierRegistry},
 * there's no carriers table — these live in the Organization.settings JSON under
 * `carrier_profiles`, keyed by a case-insensitive carrier key (the enum value for
 * built-ins, the name for custom carriers).
 */
class CarrierProfileService
{
    private const KEY = 'carrier_profiles';

    private const FIELDS = ['import_number', 'export_number', 'login_url'];

    /** The profile for one carrier, with every field present (blank when unset). */
    public function get(Organization $org, string $key): array
    {
        $stored = $this->all($org)[$this->normalize($key)] ?? [];

        return [
            'import_number' => (string) ($stored['import_number'] ?? ''),
            'export_number' => (string) ($stored['export_number'] ?? ''),
            'login_url' => (string) ($stored['login_url'] ?? ''),
        ];
    }

    /** Upsert a carrier's profile. An all-blank profile is dropped, not stored. */
    public function set(Organization $org, string $key, array $data): void
    {
        $clean = [
            'import_number' => mb_substr(trim((string) ($data['import_number'] ?? '')), 0, 50),
            'export_number' => mb_substr(trim((string) ($data['export_number'] ?? '')), 0, 50),
            'login_url' => $this->normalizeUrl((string) ($data['login_url'] ?? '')),
        ];

        $profiles = $this->all($org);
        $k = $this->normalize($key);

        if (trim(implode('', $clean)) === '') {
            unset($profiles[$k]);
        } else {
            $profiles[$k] = $clean;
        }

        $this->persist($org, $profiles);
    }

    public function forget(Organization $org, string $key): void
    {
        $profiles = $this->all($org);
        unset($profiles[$this->normalize($key)]);
        $this->persist($org, $profiles);
    }

    /** Move a profile when a custom carrier is renamed so its details aren't stranded. */
    public function rename(Organization $org, string $from, string $to): void
    {
        $profiles = $this->all($org);
        $f = $this->normalize($from);
        $t = $this->normalize($to);

        if (isset($profiles[$f])) {
            $profiles[$t] = $profiles[$f];
            if ($f !== $t) {
                unset($profiles[$f]);
            }
            $this->persist($org, $profiles);
        }
    }

    /** @return array<string, array<string, string>> raw map keyed by normalized key */
    private function all(Organization $org): array
    {
        $stored = is_array($org->settings[self::KEY] ?? null) ? $org->settings[self::KEY] : [];

        return collect($stored)
            ->filter(fn ($v) => is_array($v))
            ->mapWithKeys(fn ($v, $k) => [$this->normalize((string) $k) => array_intersect_key($v, array_flip(self::FIELDS))])
            ->all();
    }

    private function normalize(string $key): string
    {
        return mb_strtolower(trim($key));
    }

    /** Accept a bare domain by defaulting to https; keep blanks blank. */
    private function normalizeUrl(string $url): string
    {
        $url = mb_substr(trim($url), 0, 255);
        if ($url === '') {
            return '';
        }

        return preg_match('#^https?://#i', $url) ? $url : 'https://'.$url;
    }

    private function persist(Organization $org, array $profiles): void
    {
        $settings = is_array($org->settings) ? $org->settings : [];
        $settings[self::KEY] = $profiles;
        $org->update(['settings' => $settings]);
    }
}
