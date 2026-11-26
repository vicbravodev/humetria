import { TooltipProvider, WaterfallChart } from 'humetria';

export const FirstYearCost = () => (
    <TooltipProvider>
        <div className="card p-6">
            <WaterfallChart
                label="Cascada del costo del primer año por concepto"
                bars={[
                    { key: 'salary', label: 'Salario', valueText: '$1,248,000', tooltip: 'Salario: $1,248,000 · 73.3 % del primer año', from: 0, to: 0.7333, total: false },
                    { key: 'imss', label: 'Cuotas del IMSS', valueText: '$287,400', tooltip: 'Cuotas del IMSS: $287,400 · 16.9 % del primer año', from: 0.7333, to: 0.9022, total: false },
                    { key: 'infonavit', label: 'INFONAVIT', valueText: '$62,400', tooltip: 'INFONAVIT: $62,400 · 3.7 % del primer año', from: 0.9022, to: 0.9388, total: false },
                    { key: 'bonus', label: 'Aguinaldo', valueText: '$51,300', tooltip: 'Aguinaldo: $51,300 · 3 % del primer año', from: 0.9388, to: 0.969, total: false },
                    { key: 'isn', label: 'ISN', valueText: '$37,400', tooltip: 'ISN: $37,400 · 2.2 % del primer año', from: 0.969, to: 0.991, total: false },
                    { key: 'vacation', label: 'Prima vacacional', valueText: '$15,400', tooltip: 'Prima vacacional: $15,400 · 0.9 % del primer año', from: 0.991, to: 1, total: false },
                    { key: 'total', label: 'Primer año', valueText: '$1,701,900', tooltip: 'Total del primer año: $1,701,900', from: 0, to: 1, total: true },
                ]}
            />
        </div>
    </TooltipProvider>
);

export const WithSavings = () => (
    <TooltipProvider>
        <div className="card p-6">
            <WaterfallChart
                label="Cascada del costo del primer año por concepto"
                bars={[
                    { key: 'salary', label: 'Salario', valueText: '$612,000', tooltip: 'Salario: $612,000 · 98 % del primer año', from: 0, to: 0.7568, total: false },
                    { key: 'imss', label: 'Cuotas del IMSS', valueText: '$140,900', tooltip: 'Cuotas del IMSS: $140,900 · 22.6 % del primer año', from: 0.7568, to: 0.931, total: false },
                    { key: 'infonavit', label: 'INFONAVIT', valueText: '$30,600', tooltip: 'INFONAVIT: $30,600 · 4.9 % del primer año', from: 0.931, to: 0.9688, total: false },
                    { key: 'bonus', label: 'Aguinaldo', valueText: '$25,200', tooltip: 'Aguinaldo: $25,200 · 4 % del primer año', from: 0.9688, to: 1, total: false },
                    { key: 'productivity', label: 'Bono de productividad', valueText: '−$184,000', tooltip: 'Bono de productividad: −$184,000 · −29.5 % del primer año', from: 0.7725, to: 1, total: false },
                    { key: 'total', label: 'Primer año', valueText: '$624,700', tooltip: 'Total del primer año: $624,700', from: 0, to: 0.7725, total: true },
                ]}
            />
        </div>
    </TooltipProvider>
);
