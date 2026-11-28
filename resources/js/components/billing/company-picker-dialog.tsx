import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AccountCompanyRow } from '@/types';

/** Asks which of the account's companies an action is for, before opening it. */
export default function CompanyPickerDialog({
    open,
    onOpenChange,
    companies,
    onPick,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    companies: AccountCompanyRow[];
    onPick: (company: AccountCompanyRow) => void;
}) {
    const [chosen, setChosen] = useState('');
    const company = companies.find(({ company_id }) => company_id === chosen);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Elige la empresa</DialogTitle>
                <DialogDescription>
                    De las empresas que paga esta cuenta.
                </DialogDescription>
                <div className="grid gap-2">
                    <Label htmlFor="billing-pick-company">Empresa</Label>
                    <Select value={chosen} onValueChange={setChosen}>
                        <SelectTrigger id="billing-pick-company">
                            <SelectValue placeholder="Elige una empresa" />
                        </SelectTrigger>
                        <SelectContent>
                            {companies.map((option) => (
                                <SelectItem
                                    key={option.company_id}
                                    value={option.company_id}
                                >
                                    {option.trade_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancelar
                    </Button>
                    <Button
                        disabled={company === undefined}
                        onClick={() => company !== undefined && onPick(company)}
                    >
                        Continuar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
