/**
 * Sort-order fields are plain text inputs, so `Number('abc')` used to reach the
 * API as NaN — which JSON-encodes to null and blows up the integer validation.
 * Both helpers keep that value an integer at every step.
 */

/** Parses a user-typed integer, falling back instead of producing NaN. */
export function toInteger(value: string | number | null | undefined, fallback = 0): number {
  const parsed = Number.parseInt(String(value ?? '').trim(), 10);

  return Number.isFinite(parsed) ? parsed : fallback;
}

/** Keeps only digits, so a sort field cannot hold text in the first place. */
export function digitsOnly(value: string): string {
  return value.replace(/[^0-9]/g, '');
}
