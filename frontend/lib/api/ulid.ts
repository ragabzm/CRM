/**
 * Minimal ULID generator.
 *
 * The API accepts a ULID or a UUID as an Idempotency-Key, but a ULID sorts by
 * creation time, which makes the idempotency table readable when debugging a
 * retry storm. That is worth ~30 lines rather than another dependency.
 *
 * @see https://github.com/ulid/spec
 */

/** Crockford base32: no I, L, O or U, so a written-down key cannot be misread. */
const ALPHABET = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";

const TIME_CHARS = 10;
const RANDOM_CHARS = 16;

function encode(value: bigint, length: number): string {
  let out = "";
  let remaining = value;

  for (let i = 0; i < length; i++) {
    out = ALPHABET[Number(remaining % 32n)] + out;
    remaining /= 32n;
  }

  return out;
}

export function ulid(now: number = Date.now()): string {
  const time = encode(BigInt(now), TIME_CHARS);

  // 80 bits of randomness, per the spec. crypto.getRandomValues is available in
  // every runtime this client targets (browser, Node 24, edge).
  const bytes = new Uint8Array(10);
  crypto.getRandomValues(bytes);

  let randomness = 0n;
  for (const byte of bytes) {
    randomness = (randomness << 8n) | BigInt(byte);
  }

  return time + encode(randomness, RANDOM_CHARS);
}

/** The shape the API's Idempotency-Key validation accepts. */
export const ULID_PATTERN = /^[0-7][0-9A-HJKMNP-TV-Z]{25}$/;
