import { describe, expect, it, vi } from 'vite-plus/test';
import { createTourRunner, driverSteps } from '@/lib/first-steps/tour';
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

describe('createTourRunner', () => {
    const tourOf = () => ({ destroy: vi.fn() });

    function deferred() {
        const tour = tourOf();
        let resolve: (value: typeof tour) => void = () => {};
        const promise = new Promise<typeof tour>((done) => {
            resolve = done;
        });

        return { tour, promise, resolve };
    }

    it('keeps a tour that loaded and destroys it on cancel', async () => {
        const tour = tourOf();
        const runner = createTourRunner(() => Promise.resolve(tour));

        runner.run(null);
        await flush();
        expect(tour.destroy).not.toHaveBeenCalled();

        runner.cancel();
        expect(tour.destroy).toHaveBeenCalledTimes(1);
    });

    it('destroys a tour that loads after the cancel', async () => {
        const late = deferred();
        const runner = createTourRunner(() => late.promise);

        runner.run(null);
        runner.cancel();
        late.resolve(late.tour);
        await flush();

        expect(late.tour.destroy).toHaveBeenCalledTimes(1);
    });

    it('destroys the first tour when a second run supersedes it', async () => {
        const first = deferred();
        const second = tourOf();
        const loads = [first.promise, Promise.resolve(second)];
        const runner = createTourRunner(
            () => loads.shift() ?? Promise.reject(new Error('none')),
        );

        runner.run(null);
        runner.run(null);
        first.resolve(first.tour);
        await flush();

        expect(first.tour.destroy).toHaveBeenCalledTimes(1);
        expect(second.destroy).not.toHaveBeenCalled();
    });

    it('destroys the running tour when a new run starts', async () => {
        const first = tourOf();
        const loads = [Promise.resolve(first), Promise.resolve(tourOf())];
        const runner = createTourRunner(
            () => loads.shift() ?? Promise.reject(new Error('none')),
        );

        runner.run(null);
        await flush();
        runner.run(null);

        expect(first.destroy).toHaveBeenCalledTimes(1);
    });

    it('swallows a failed load', async () => {
        const runner = createTourRunner(() =>
            Promise.reject(new Error('chunk')),
        );

        expect(() => runner.run(null)).not.toThrow();
        await flush();
        expect(() => runner.cancel()).not.toThrow();
    });
});

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
