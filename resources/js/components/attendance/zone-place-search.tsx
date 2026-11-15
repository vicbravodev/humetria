import { MapPin, Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { usePlaceSearch } from '@/hooks/use-place-search';
import type { Place } from '@/lib/attendance/zone-picker';

/**
 * «Buscar una dirección» over the map: on Enter or the button, never while typing; each place found is a button that
 * puts the zone's center there. Without a name, the field is not part of the zone's form.
 */
export default function ZonePlaceSearch({
    url,
    onPick,
}: {
    url: string;
    onPick: (place: Place) => void;
}) {
    const [address, setAddress] = useState('');
    const { busy, places, failed, search, clear } = usePlaceSearch(url);

    return (
        <div className="grid gap-2">
            <div className="flex gap-2">
                <Input
                    aria-label="Buscar una dirección"
                    placeholder="Calle, número, colonia o ciudad"
                    value={address}
                    maxLength={120}
                    onChange={(event) => setAddress(event.target.value)}
                    onKeyDown={(event) => {
                        // Enter searches; it must not send the zone's form.
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            void search(address);
                        }
                    }}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label="Buscar dirección"
                    disabled={busy}
                    onClick={() => void search(address)}
                >
                    {busy ? <Spinner /> : <Search aria-hidden />}
                </Button>
            </div>
            <div role="status" className="empty:hidden">
                {failed && (
                    <p className="text-sm text-destructive">
                        No pudimos buscar la dirección. Toca el mapa o usa tu
                        ubicación.
                    </p>
                )}
                {places?.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No encontramos esa dirección. Prueba con la calle y la
                        ciudad, o toca el mapa.
                    </p>
                )}
            </div>
            {places !== null && places.length > 0 && (
                <ul className="divide-y overflow-hidden card">
                    {places.map((place) => (
                        <li key={`${place.latitude},${place.longitude}`}>
                            <button
                                type="button"
                                className="flex w-full items-start gap-2 px-3 py-2.5 text-left text-sm hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
                                onClick={() => {
                                    onPick(place);
                                    clear();
                                }}
                            >
                                <MapPin
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                {place.name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
