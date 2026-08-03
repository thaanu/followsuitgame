import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * A plain deep copy of a value.
 *
 * `structuredClone` refuses Vue's reactive proxies, which is what Inertia hands
 * us for page props, so rule objects are copied through JSON instead.
 */
export function deepClone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value)) as T;
}
