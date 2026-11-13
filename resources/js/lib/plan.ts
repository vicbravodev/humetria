import type { PlanTool, PlanUse } from '@/types';

/** What the owner reads under a meter: how much is left of the plan, or that nothing limits it. */
export function useLine(
    use: PlanUse,
    noun: { one: string; many: string },
): string {
    if (use.limit === null) {
        return `Sin tope: usas ${use.used} ${use.used === 1 ? noun.one : noun.many}.`;
    }

    const left = use.limit - use.used;

    if (left < 0) {
        return `Tienes ${-left} de más: conservas lo que tienes y para agregar hay que ampliar el plan.`;
    }

    if (left === 0) {
        return 'Ya usas todo lo que cubre tu plan: para agregar más hay que ampliarlo.';
    }

    return `${left === 1 ? 'Queda' : 'Quedan'} ${left} ${left === 1 ? noun.one : noun.many}.`;
}

/** What a tool says about itself: on, on until a day, or off. */
export function toolState(tool: PlanTool): string {
    if (!tool.active) {
        return 'No incluida';
    }

    return tool.endsOn === null
        ? 'Incluida'
        : `Incluida hasta el ${tool.endsOn}`;
}
