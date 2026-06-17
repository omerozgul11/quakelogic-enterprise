<?php

namespace App\Services\Proposals;

use App\Models\Organization;

/**
 * The organization's proposal "style profile" — the tone, voice, boilerplate,
 * win themes, writing rules and document formatting (fonts/spacing/margins) that
 * the AI Proposal Writer follows and the Word/PDF export applies. Stored in the
 * unused Organization.settings JSON column under the `proposal_style` key.
 */
class ProposalStyleService
{
    public const DEFAULTS = [
        'tone' => '',
        'voice' => 'First person plural (we/our), confident and professional',
        'company_background' => '',
        'win_themes' => '',
        'writing_rules' => '',
        'format' => [
            'font' => 'Helvetica Neue',     // body — matches the QuakeLogic template
            'heading_font' => 'Raleway',     // headings
            'font_size' => 10.5,             // points
            'line_spacing' => 1.5,
            'margin_inches' => 0.85,
            'accent_color' => 'F26522',      // QuakeLogic orange
        ],
    ];

    /** @return array<string,mixed> the profile, merged with defaults */
    public function get(Organization $org): array
    {
        $stored = is_array($org->settings['proposal_style'] ?? null) ? $org->settings['proposal_style'] : [];

        return [
            ...self::DEFAULTS,
            ...array_intersect_key($stored, self::DEFAULTS),
            'format' => array_merge(
                self::DEFAULTS['format'],
                is_array($stored['format'] ?? null) ? array_intersect_key($stored['format'], self::DEFAULTS['format']) : [],
            ),
        ];
    }

    /** @param array<string,mixed> $data */
    public function save(Organization $org, array $data): void
    {
        $settings = is_array($org->settings) ? $org->settings : [];
        $settings['proposal_style'] = [
            ...self::DEFAULTS,
            ...array_intersect_key($data, self::DEFAULTS),
            'format' => array_merge(self::DEFAULTS['format'], is_array($data['format'] ?? null) ? $data['format'] : []),
        ];
        $org->update(['settings' => $settings]);
    }

    /** Whether the org has filled in any meaningful style guidance. */
    public function isConfigured(Organization $org): bool
    {
        $p = $this->get($org);
        return trim((string) $p['tone']) !== ''
            || trim((string) $p['company_background']) !== ''
            || trim((string) $p['win_themes']) !== ''
            || trim((string) $p['writing_rules']) !== '';
    }

    /** Human-readable style guidance for the writer prompt ('' when unset). */
    public function promptBlock(Organization $org): string
    {
        $p = $this->get($org);

        return implode("\n", array_filter([
            trim((string) $p['tone']) !== '' ? 'Tone: ' . $p['tone'] : null,
            trim((string) $p['voice']) !== '' ? 'Voice: ' . $p['voice'] : null,
            trim((string) $p['company_background']) !== '' ? 'Company background: ' . $p['company_background'] : null,
            trim((string) $p['win_themes']) !== '' ? 'Win themes to weave in where relevant: ' . $p['win_themes'] : null,
            trim((string) $p['writing_rules']) !== '' ? 'Writing rules to follow: ' . $p['writing_rules'] : null,
        ]));
    }
}
