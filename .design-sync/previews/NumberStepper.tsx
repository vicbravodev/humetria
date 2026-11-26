import { NumberStepper } from 'humetria';

export const Raise = () => (
    <div className="max-w-xs">
        <NumberStepper
            id="unionized_raise"
            name="unionized_raise"
            label="Aumento"
            value="4.5"
            suffix="%"
            hint="Aplica a 142 personas sindicalizadas"
            onChange={() => {}}
            onStep={() => {}}
        />
    </div>
);

export const EmptyWithPlaceholder = () => (
    <div className="max-w-xs">
        <NumberStepper
            id="retroactive_months"
            name="retroactive_months"
            label="Meses de retroactivo"
            value=""
            placeholder="0"
            suffix="meses"
            hint="Vacío: sin retroactivo"
            onChange={() => {}}
            onStep={() => {}}
        />
    </div>
);

export const WithError = () => (
    <div className="max-w-xs">
        <NumberStepper
            id="group_minimum"
            name="group_minimum"
            label="Mínimo por grupo"
            value="60"
            suffix="personas"
            error="El mínimo va de 1 a 50 personas."
            onChange={() => {}}
            onStep={() => {}}
        />
    </div>
);
