import BenefitName from '@/components/compensation/benefit-name';
import BenefitRowActions from '@/components/compensation/benefit-row-actions';
import BenefitValue from '@/components/compensation/benefit-value';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { BenefitChoices, BenefitRow } from '@/types';

/** The catalog: what each benefit is, what it is worth today, who it is for and how it weighs on the SBC. */
export default function BenefitsTable({
    benefits,
    choices,
}: {
    benefits: BenefitRow[];
    choices: BenefitChoices;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Prestaciones, las base primero y luego por nombre
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Prestación</TableHead>
                    <TableHead>Vigente hoy</TableHead>
                    <TableHead>A quién aplica</TableHead>
                    <TableHead>Salario base de cotización</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {benefits.map((benefit) => (
                    <TableRow key={benefit.id}>
                        <TableCell className="whitespace-normal">
                            <BenefitName benefit={benefit} />
                        </TableCell>
                        <TableCell className="max-w-72 whitespace-normal">
                            <BenefitValue benefit={benefit} />
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {benefit.group_label}
                            {benefit.grades.length > 0 &&
                                ` · ${benefit.grades.length} categorías`}
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {benefit.sbc_integration_label}
                        </TableCell>
                        <TableCell className="w-px text-right">
                            <BenefitRowActions
                                benefit={benefit}
                                choices={choices}
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
