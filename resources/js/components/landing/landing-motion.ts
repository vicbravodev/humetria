import type { Transition } from 'motion/react';

/** The design system's strong ease-out (`--ease-out`): things that enter start fast and settle. */
export const EASE_OUT: [number, number, number, number] = [0.23, 1, 0.32, 1];

/** How a part of the landing enters the first time it scrolls into view. */
export const ENTER: Transition = { duration: 0.6, ease: EASE_OUT };

/** Animations that play once, when a fifth of the part shows. */
export const ONCE = { once: true, amount: 0.2 };
