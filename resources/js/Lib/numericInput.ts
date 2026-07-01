// App-wide guard that keeps numeric fields numeric.
//
// A native <input type="number"> (and any field marked inputMode="decimal")
// still lets users type `e`, `+`, `-`, and multiple dots. This intercepts input
// at the `beforeinput` stage — before the character is inserted and before
// React's onChange runs — and blocks anything that isn't a digit or a single
// decimal point. Deletes, arrow keys, undo/redo and the like are left alone.

function isNumericField(el: EventTarget | null): el is HTMLInputElement {
    return el instanceof HTMLInputElement
        && (el.type === 'number' || el.inputMode === 'decimal');
}

// The text this event is trying to insert — from typing (`data`) or from a
// paste/drop (`dataTransfer`).
function insertedText(event: InputEvent): string {
    if (event.data != null) {
        return event.data;
    }
    return event.dataTransfer?.getData('text') ?? '';
}

function guard(event: InputEvent): void {
    const el = event.target;
    if (!isNumericField(el)) {
        return;
    }

    // Only police insertions; allow deletions, moves, undo/redo, formatting.
    if (!event.inputType?.startsWith('insert')) {
        return;
    }

    const data = insertedText(event);
    if (data === '') {
        return; // e.g. inserting a line break — nothing to validate
    }

    // Reject outright anything that isn't a digit or a dot.
    if (!/^[0-9.]*$/.test(data)) {
        event.preventDefault();
        return;
    }

    // Allow at most one decimal point in the resulting value.
    if (data.includes('.')) {
        let current = el.value;
        // type=number doesn't expose a selection range (returns null / throws),
        // so fall back to the whole value there; for decimal text fields, drop
        // the selection this insert would replace.
        try {
            const { selectionStart, selectionEnd } = el;
            if (selectionStart != null && selectionEnd != null) {
                current = current.slice(0, selectionStart) + current.slice(selectionEnd);
            }
        } catch {
            /* selection API unsupported on this input type — use the full value */
        }
        const dots = (current.match(/\./g)?.length ?? 0) + (data.match(/\./g)?.length ?? 0);
        if (dots > 1) {
            event.preventDefault();
        }
    }
}

/** Install the numeric-input guard once, for the whole document. */
export function installNumericInputGuard(): void {
    document.addEventListener('beforeinput', guard as EventListener);
}
