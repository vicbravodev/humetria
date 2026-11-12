import { describe, expect, it } from 'vite-plus/test';
import { driverSteps } from '@/lib/first-steps/tour';
import type { TourBubble } from '@/lib/first-steps/steps';

const bubbles: TourBubble[] = [
    {
        target: 'import-employees',
        title: 'Importa tu plantilla',
        text: 'Sube un CSV.',
    },
    { target: 'add-employee', title: 'O una por una', text: 'A mano.' },
];

describe('driverSteps', () => {
    it('points each bubble at its control', () => {
        const steps = driverSteps(bubbles, () => true);

        expect(steps[0]).toMatchObject({
            element: '[data-tour="import-employees"]',
            popover: {
                title: 'Importa tu plantilla',
                description: 'Sube un CSV.',
            },
        });
        expect(steps[1]).toMatchObject({
            element: '[data-tour="add-employee"]',
        });
    });

    it('centers a bubble whose control is not on the page', () => {
        const steps = driverSteps(bubbles, (selector) =>
            selector.includes('add-employee'),
        );

        expect(steps[0]).not.toHaveProperty('element');
        expect(steps[0]).toMatchObject({
            popover: { title: 'Importa tu plantilla' },
        });
        expect(steps[1]).toHaveProperty('element');
    });
});
