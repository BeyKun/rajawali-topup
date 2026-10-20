import { nextTick } from 'vue';

/**
 * Format Serial Number with spaces every 4 digits.
 * Example: '300338120354' -> '3003 3812 0354'
 * Example (15 digits): '300338120354186' -> '3003 3812 0354 186'
 */
export function formatSerialNumber(val: string | null | undefined): string {
    if (!val) return '';
    const digits = val.replace(/\D/g, '');
    if (!digits) return '';
    return digits.match(/.{1,4}/g)?.join(' ') ?? digits;
}

/**
 * Format Voucher HRN / Secret Code with spaces.
 * Format for 17-digit Telkomsel: 5-5-5-2 (e.g. '71125 61343 18480 01')
 * Format for 16-digit: 4-4-4-4 (e.g. '1234 5678 9012 3456')
 * Format for 14-digit: 4-4-4-2 (e.g. '1234 5678 9012 34')
 */
export function formatHrn(val: string | null | undefined): string {
    if (!val) return '';
    const digits = val.replace(/\D/g, '');
    if (!digits) return '';

    // Standard 16-digit voucher
    if (digits.length === 16) {
        return digits.match(/.{1,4}/g)?.join(' ') ?? digits;
    }
    // Standard 14-digit voucher
    if (digits.length === 14) {
        return digits.match(/.{1,4}/g)?.join(' ') ?? digits;
    }
    // 17-digit Telkomsel voucher (chunks of 5, ending with 2)
    if (digits.length >= 17) {
        return digits.slice(0, 17).match(/.{1,5}/g)?.join(' ') ?? digits;
    }
    // Typing towards 17 digits (15-16 in progress)
    if (digits.length >= 15) {
        return digits.match(/.{1,5}/g)?.join(' ') ?? digits;
    }
    // General / shorter: chunk by 4
    return digits.match(/.{1,4}/g)?.join(' ') ?? digits;
}

/**
 * Clean all spaces and non-digit characters for pure storage/submission.
 */
export function cleanDigits(val: string | null | undefined): string {
    if (!val) return '';
    return val.replace(/\D/g, '');
}

/**
 * Helper to handle input formatting with natural cursor preservation.
 */
export function handleFormattedInput(
    event: Event,
    formatFn: (val: string) => string,
    updateState: (formattedVal: string) => void,
): void {
    const input = event.target as HTMLInputElement;
    if (!input) return;

    const rawVal = input.value;
    const oldCursor = input.selectionStart ?? rawVal.length;

    // Count how many non-space characters were before the cursor
    const digitsBefore = rawVal.slice(0, oldCursor).replace(/\s+/g, '').length;

    const formatted = formatFn(rawVal);
    updateState(formatted);

    // Synchronize the input element value immediately if v-model is lagged
    input.value = formatted;

    nextTick(() => {
        let newCursor = 0;
        let count = 0;
        for (let i = 0; i < formatted.length; i++) {
            if (formatted[i] !== ' ') {
                count++;
            }
            if (count === digitsBefore) {
                newCursor = i + 1;
                break;
            }
        }
        if (digitsBefore === 0) newCursor = 0;
        input.setSelectionRange(newCursor, newCursor);
    });
}
