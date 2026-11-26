import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
    Initials,
    Logo,
    PageHeader,
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    TooltipProvider,
} from 'humetria';
import {
    Armchair,
    Briefcase,
    ChevronRight,
    ClipboardCheck,
    Ear,
    Factory,
    House,
    Laptop,
    type LucideIcon,
    Settings2,
    ShieldCheck,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';

// Mirrors nav-group.tsx: a family of tools folds under its name, its tools hang from a guide line.
const Family = ({
    label,
    icon: Icon,
    badge = 0,
    open = false,
    children,
}: {
    label: string;
    icon: LucideIcon;
    badge?: number;
    open?: boolean;
    children: ReactNode;
}) => (
    <Collapsible asChild defaultOpen={open}>
        <SidebarGroup className="group/family py-0">
            <SidebarGroupLabel
                asChild
                className="h-11 w-full gap-2.5 rounded-lg text-left text-base font-semibold tracking-normal text-sidebar-foreground normal-case transition-colors hover:bg-secondary [&>svg]:size-5 [&>svg:first-child]:text-muted-foreground [&>svg:last-child]:size-4"
            >
                <CollapsibleTrigger>
                    <Icon aria-hidden />
                    <span className="truncate">{label}</span>
                    {badge > 0 && (
                        <span
                            data-sidebar="group-badge"
                            className="rounded-full bg-sidebar-accent px-[7px] text-xs leading-5 font-medium tracking-normal text-sidebar-foreground tabular-nums group-data-[state=open]/family:hidden"
                        >
                            {badge}
                        </span>
                    )}
                    <ChevronRight className="ml-auto transition-transform duration-200 ease-out group-data-[state=open]/family:rotate-90" />
                </CollapsibleTrigger>
            </SidebarGroupLabel>
            <CollapsibleContent className="ml-[21px] border-l border-sidebar-border pl-1.5">{children}</CollapsibleContent>
        </SidebarGroup>
    </Collapsible>
);

const Item = ({ title, icon: Icon, active = false, badge }: { title: string; icon: LucideIcon; active?: boolean; badge?: number }) => (
    <SidebarMenuItem>
        <SidebarMenuButton asChild isActive={active} tooltip={title}>
            <a href="#" aria-current={active ? 'page' : undefined}>
                <Icon />
                <span>{title}</span>
            </a>
        </SidebarMenuButton>
        {badge !== undefined && <SidebarMenuBadge>{badge}</SidebarMenuBadge>}
    </SidebarMenuItem>
);

// Mirrors nav-submenu.tsx: the parent opens its pages, only the page's child is a pill.
const Telework = ({ lit }: { lit?: string }) => (
    <Collapsible asChild defaultOpen={lit !== undefined}>
        <SidebarMenuItem>
            <CollapsibleTrigger asChild>
                <SidebarMenuButton className="data-[state=open]:font-semibold data-[state=open]:[&>svg:first-child]:text-sidebar-primary data-[state=open]:[&>svg:last-child]:rotate-90">
                    <Laptop />
                    <span className="truncate">NOM-037</span>
                    <ChevronRight className="ml-auto size-4! transition-transform duration-200 ease-out" />
                </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <SidebarMenuSub>
                    {['Centros', 'Documentos', 'Capacitación', 'Avisos'].map((page) => (
                        <SidebarMenuSubItem key={page}>
                            <SidebarMenuSubButton asChild isActive={page === lit}>
                                <a href="#" aria-current={page === lit ? 'page' : undefined}>
                                    <span>{page}</span>
                                </a>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    ))}
                </SidebarMenuSub>
            </CollapsibleContent>
        </SidebarMenuItem>
    </Collapsible>
);

const Frame = ({ page, lit, title, caption }: { page?: string; lit?: string; title: string; caption: string }) => (
    <TooltipProvider>
    <SidebarProvider>
        <Sidebar variant="inset" collapsible="icon">
            <SidebarHeader className="px-3 pt-4 pb-4">
                <a href="#" className="flex min-h-11 w-fit items-center rounded-md px-1.5">
                    <Logo className="h-7" />
                </a>
            </SidebarHeader>
            <SidebarContent className="gap-4 px-1">
                <SidebarGroup className="py-0">
                    <SidebarMenu className="gap-0.5">
                        <Item title="Panel" icon={House} active={page === 'Panel'} />
                        <Item title="Centros de trabajo" icon={Factory} active={page === 'Centros de trabajo'} />
                        <Item title="Empleados" icon={Users} active={page === 'Empleados'} />
                    </SidebarMenu>
                </SidebarGroup>
                <Family label="Administración" icon={Settings2}>
                    <SidebarMenu className="gap-0.5">
                        <Item title="Usuarios" icon={Users} />
                    </SidebarMenu>
                </Family>
                <Family label="Cumple la norma" icon={ShieldCheck} badge={2} open>
                    <SidebarMenu className="gap-0.5">
                        <Item title="NOM-035" icon={ClipboardCheck} badge={2} />
                        <Telework {...(lit === undefined ? {} : { lit })} />
                        <Item title="Ley Silla" icon={Armchair} />
                    </SidebarMenu>
                </Family>
                <Family label="Escucha a tu gente" icon={Ear}>
                    <SidebarMenu className="gap-0.5">
                        <Item title="Clima" icon={Ear} />
                    </SidebarMenu>
                </Family>
                <Family label="Ordena tu operación" icon={Briefcase}>
                    <SidebarMenu className="gap-0.5">
                        <Item title="Asistencia" icon={Briefcase} />
                    </SidebarMenu>
                </Family>
            </SidebarContent>
            <SidebarFooter className="mx-3 border-t border-sidebar-border px-0 py-4">
                <div className="flex items-center gap-3">
                    <Initials name="María Fernanda López" />
                    <div className="grid">
                        <span className="text-sm font-medium">María Fernanda López</span>
                        <span className="text-xs text-muted-foreground">Dueña · Grupo Industrial Norteño</span>
                    </div>
                </div>
            </SidebarFooter>
        </Sidebar>
        <SidebarInset>
            <div className="p-6">
                <PageHeader caption={caption} title={title} />
            </div>
        </SidebarInset>
    </SidebarProvider>
    </TooltipProvider>
);

export const OnThePanel = () => <Frame page="Panel" title="Panel" caption="Grupo Industrial Norteño" />;

export const InASubmenuPage = () => <Frame lit="Documentos" title="Documentos de teletrabajo" caption="NOM-037" />;
