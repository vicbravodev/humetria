import {
    animate,
    useMotionValue,
    useReducedMotion,
    useTransform,
    type MotionValue,
} from 'motion/react';
import { useEffect, useRef } from 'react';
import { formatPesos } from '@/lib/pricing';

/** Overdamped on purpose: it settles without bouncing past the amount, so the figure never shows a number below zero or above the total. */
const SPRING = { type: 'spring', stiffness: 120, damping: 30 } as const;

/**
 * An amount of pesos that moves to its new value with a spring instead of jumping (feedback for the person changing the
 * headcount), and counts up from zero the first time `visible` turns true (the figure arrives as its card does). It lives in a
 * motion value, so no frame re-renders React. With reduced motion it is always the amount itself.
 */
export function useAnimatedPesos(
    target: number,
    visible: boolean,
): MotionValue<string> {
    const reduce = useReducedMotion();
    const value = useMotionValue(target);
    const counted = useRef(false);

    useEffect(() => {
        if (reduce === true || !visible) {
            value.jump(target);

            return;
        }

        if (!counted.current) {
            counted.current = true;
            value.jump(0);
        }

        const controls = animate(value, target, SPRING);

        return () => controls.stop();
    }, [target, visible, reduce, value]);

    return useTransform(value, (amount) =>
        formatPesos(Math.max(0, Math.round(amount))),
    );
}
