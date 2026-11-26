import { Button, Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from 'humetria';
import { Sparkles } from 'lucide-react';

export const OnIconButton = () => (
    <div className="flex items-center" style={{ justifyContent: 'center', paddingTop: 72 }}>
        <TooltipProvider>
            <Tooltip open>
                <TooltipTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label="Pregúntale a Humi">
                        <Sparkles />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="top">Pregúntale a Humi</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    </div>
);
