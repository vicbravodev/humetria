import GrowBar from '@/components/landing/grow-bar';

const posts = [
    { name: 'Cajas', seats: 6, standing: 6 },
    { name: 'Piso de venta', seats: 5, standing: 8 },
    { name: 'Almacén', seats: 1, standing: 4 },
] as const;

/** The posts worked standing, and how many of them already have a seat. */
export default function VisualLeySilla() {
    return (
        <div className="flex flex-col gap-4 card p-4">
            <p className="text-sm font-medium">Asientos por puesto de pie</p>
            <ul className="flex flex-col gap-3">
                {posts.map((post, index) => (
                    <li key={post.name} className="flex flex-col gap-1.5">
                        <span className="flex justify-between text-sm">
                            <span>{post.name}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {post.seats} de {post.standing}
                            </span>
                        </span>
                        <GrowBar
                            share={post.seats / post.standing}
                            delay={index * 0.1}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}
