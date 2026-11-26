import { Pagination } from 'humetria';

const employees = (current: number) => ({
    data: [],
    total: 186,
    from: (current - 1) * 25 + 1,
    to: Math.min(current * 25, 186),
    current_page: current,
    last_page: 8,
    prev_page_url: current > 1 ? `/admin/acme/empleados?page=${current - 1}` : null,
    next_page_url: current < 8 ? `/admin/acme/empleados?page=${current + 1}` : null,
});

export const FirstPage = () => <Pagination page={employees(1)} label="Páginas de empleados" pinned={false} />;

export const MiddlePage = () => <Pagination page={employees(4)} label="Páginas de empleados" pinned={false} />;

export const LastPage = () => <Pagination page={employees(8)} label="Páginas de empleados" pinned={false} />;

export const CustomLabels = () => (
    <Pagination
        page={{
            data: [],
            total: 63,
            from: 21,
            to: 40,
            current_page: 2,
            last_page: 4,
            prev_page_url: '/admin/acme/difusion/publicaciones?page=1',
            next_page_url: '/admin/acme/difusion/publicaciones?page=3',
        }}
        label="Páginas de publicaciones"
        previous="Ver más recientes"
        next="Ver más antiguas"
        pinned={false}
    />
);
