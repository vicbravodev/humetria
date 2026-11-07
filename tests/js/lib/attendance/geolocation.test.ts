import { describe, expect, it, vi } from 'vitest';
import { currentPosition } from '@/lib/attendance/geolocation';

/** A device's geolocation that answers with `position` or fails with the error `code`. */
function deviceAnswering(answer: { code: number } | GeolocationCoordinates) {
    const getCurrentPosition = vi.fn<Geolocation['getCurrentPosition']>(
        (success, failure) => {
            if ('code' in answer) {
                failure?.({
                    code: answer.code,
                    message: '',
                    PERMISSION_DENIED: 1,
                    POSITION_UNAVAILABLE: 2,
                    TIMEOUT: 3,
                });
            } else {
                success({
                    coords: answer,
                    timestamp: 0,
                    toJSON: () => ({}),
                });
            }
        },
    );

    return {
        geolocation: {
            getCurrentPosition,
            watchPosition: vi.fn(),
            clearWatch: vi.fn(),
        },
        getCurrentPosition,
    };
}

const coords: GeolocationCoordinates = {
    latitude: 19.4327,
    longitude: -99.1332,
    accuracy: 12.5,
    altitude: null,
    altitudeAccuracy: null,
    heading: null,
    speed: null,
    toJSON: () => ({}),
};

describe('currentPosition', () => {
    it('asks once, accurately and fresh, and gives the position with its accuracy', async () => {
        const { geolocation, getCurrentPosition } = deviceAnswering(coords);

        await expect(currentPosition(geolocation, 8000)).resolves.toEqual({
            ok: true,
            position: { lat: 19.4327, lng: -99.1332, accuracy: 12.5 },
        });
        expect(getCurrentPosition).toHaveBeenCalledOnce();
        expect(getCurrentPosition.mock.calls[0]?.[2]).toEqual({
            enableHighAccuracy: true,
            timeout: 8000,
            maximumAge: 0,
        });
    });

    it('waits 15 seconds by default', async () => {
        const { geolocation, getCurrentPosition } = deviceAnswering(coords);

        await currentPosition(geolocation);

        expect(getCurrentPosition.mock.calls[0]?.[2]?.timeout).toBe(15000);
    });

    it.each([
        [1, 'denied'],
        [2, 'unavailable'],
        [3, 'timeout'],
        [99, 'unavailable'],
    ])('names the error %i as %s', async (code, error) => {
        await expect(
            currentPosition(deviceAnswering({ code }).geolocation),
        ).resolves.toEqual({ ok: false, error });
    });

    it('is unsupported where the browser has no geolocation', async () => {
        await expect(currentPosition(undefined)).resolves.toEqual({
            ok: false,
            error: 'unsupported',
        });
    });

    it('is unavailable when asking throws', async () => {
        const geolocation = {
            getCurrentPosition: () => {
                throw new Error('blocked');
            },
            watchPosition: vi.fn(),
            clearWatch: vi.fn(),
        };

        await expect(currentPosition(geolocation)).resolves.toEqual({
            ok: false,
            error: 'unavailable',
        });
    });
});
