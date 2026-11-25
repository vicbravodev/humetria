import { QRCanvas, frameLoop, rearCamera } from 'qr/dom.js';
import { useCallback, useEffect, useRef, useState } from 'react';
import { cameraFailure, firstText, punchCode } from '@/lib/pwa/qr-scan';
import type { ScanStatus } from '@/lib/pwa/qr-scan';
import { quietWatch } from '@/lib/pwa/quiet-watch';

export type { ScanStatus };

/**
 * Opens the rear camera on `video` and reads a frame at a time (never two at once) until stopped. Gives what stops it: the
 * loop, the camera (its indicator goes off) and anything still waiting for a frame.
 */
async function openCamera(
    video: HTMLVideoElement,
    onText: (text: string) => void,
): Promise<() => void> {
    const camera = await rearCamera(video);
    const canvas = new QRCanvas();
    let live = true;
    let busy = false;
    const cancel = frameLoop(() => {
        if (busy || !live) {
            return;
        }

        busy = true;
        // The camera's own pixels (`true`), not the size the video is drawn at on the screen: a dense code seen from a phone is
        // only a few pixels per square at that size.
        camera
            .readFrame(canvas, true)
            .then((read) => {
                const text = firstText(read);

                if (live && text !== undefined) {
                    onText(text);
                }
            })
            .catch(() => undefined)
            .finally(() => {
                busy = false;
            });
    }, video);

    return () => {
        live = false;
        cancel();
        camera.stop();
    };
}

/**
 * Reads the QR of the screen of the person's center with the phone's camera, inside the page. The code of this company's
 * Marcar address goes to `onCode` and the camera is turned off at once; one of another company is said (`other-company`)
 * and the reading goes on. The camera never outlives the screen: stopping, leaving the page, hiding it or unmounting
 * turns it off.
 */
export function useQrScanner({
    slug,
    onCode,
}: {
    slug: string;
    onCode: (code: string) => void;
}) {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const release = useRef<() => void>(() => undefined);
    // Which start is the current one: a camera that opens after a stop is turned off as soon as it arrives.
    const generation = useRef(0);
    const found = useRef(onCode);
    const [status, setStatus] = useState<ScanStatus>('idle');
    // Nothing read for a while: said, and the reading goes on. Anything read, right or wrong, ends the wait.
    const quiet = useRef(
        quietWatch(() =>
            setStatus((now) => (now === 'scanning' ? 'unreadable' : now)),
        ),
    );

    useEffect(() => {
        found.current = onCode;
    });

    const stop = useCallback(() => {
        quiet.current.clear();
        generation.current += 1;
        release.current();
        release.current = () => undefined;
        setStatus('idle');
    }, []);

    const start = useCallback(async () => {
        const video = videoRef.current;

        if (video === null) {
            return;
        }

        generation.current += 1;
        const mine = generation.current;

        if (!('mediaDevices' in navigator)) {
            setStatus('unavailable');

            return;
        }

        setStatus('opening');

        try {
            const off = await openCamera(video, (text) => {
                const read = punchCode(text, {
                    origin: location.origin,
                    slug,
                });

                quiet.current.clear();

                if (read === 'other-company' || read === 'unrecognized') {
                    setStatus(read);
                } else {
                    stop();
                    found.current(read.code);
                }
            });

            if (generation.current !== mine) {
                off();

                return;
            }

            release.current = off;
            setStatus('scanning');
            quiet.current.arm();
        } catch (error) {
            if (generation.current === mine) {
                setStatus(
                    cameraFailure(error) === 'denied'
                        ? 'denied'
                        : 'unavailable',
                );
            }
        }
    }, [slug, stop]);

    useEffect(() => {
        const hidden = () => {
            if (document.visibilityState === 'hidden') {
                stop();
            }
        };
        window.addEventListener('pagehide', stop);
        document.addEventListener('visibilitychange', hidden);

        return () => {
            window.removeEventListener('pagehide', stop);
            document.removeEventListener('visibilitychange', hidden);
            stop();
        };
    }, [stop]);

    return { videoRef, start, stop, status };
}
