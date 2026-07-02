import { router } from '@inertiajs/react';

/**
 * Makes whole table rows clickable. Any `<tr class="row-link">` becomes openable
 * by clicking anywhere in it — not just the number/title link — by activating
 * the row's primary link. Zero markup change is needed on the tables: the target
 * is the row's own detail link (its first `<a href>`), or an explicit
 * `data-row-href` on the row when that heuristic doesn't fit.
 *
 * Clicks that land on real controls (links, buttons, inputs, menus, or anything
 * marked `data-no-row-link`) keep their own behavior, and a text-selection drag
 * never navigates. Modifier / middle clicks open in a new tab, like a real link.
 */
const INTERACTIVE = 'a, button, input, select, textarea, label, [role="button"], [role="menuitem"], [role="checkbox"], [role="switch"], [contenteditable="true"], [data-no-row-link]';

function rowHref(row: HTMLElement): string | null {
    return row.getAttribute('data-row-href')
        ?? row.querySelector<HTMLAnchorElement>('a[href]')?.getAttribute('href')
        ?? null;
}

function rowFor(target: EventTarget | null): HTMLElement | null {
    const el = target as HTMLElement | null;
    if (!el || typeof el.closest !== 'function') {
        return null;
    }
    const row = el.closest('tr.row-link') as HTMLElement | null;
    if (!row || el.closest(INTERACTIVE)) {
        return null; // no clickable row, or the click was on a real control
    }
    return row;
}

export function installRowLinkNavigation(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0) {
            return;
        }
        const row = rowFor(e.target);
        if (!row) {
            return;
        }
        // Don't hijack a text selection the user is making inside the row.
        if ((window.getSelection()?.toString() ?? '') !== '') {
            return;
        }
        const href = rowHref(row);
        if (!href) {
            return;
        }
        if (e.metaKey || e.ctrlKey || e.shiftKey) {
            window.open(href, '_blank', 'noopener');
            return;
        }
        router.visit(href);
    });

    // Middle-click opens the row in a new tab, matching normal link behavior.
    document.addEventListener('auxclick', (e) => {
        if (e.button !== 1) {
            return;
        }
        const row = rowFor(e.target);
        const href = row ? rowHref(row) : null;
        if (href) {
            window.open(href, '_blank', 'noopener');
        }
    });
}
