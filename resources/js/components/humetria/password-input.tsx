import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { InputGroup, InputGroupInput } from '@/components/ui/input-group';

/** A password field with its show/hide button inside the same box. */
export default function PasswordInput({
    className,
    ref,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & { ref?: Ref<HTMLInputElement> }) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <InputGroup className={className}>
            <InputGroupInput
                type={showPassword ? 'text' : 'password'}
                ref={ref}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="flex h-full shrink-0 items-center rounded-r-md px-3 text-muted-foreground transition-colors duration-150 ease-out hover:text-foreground"
                aria-label={
                    showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'
                }
                tabIndex={-1}
            >
                {showPassword ? (
                    <EyeOff className="size-5" />
                ) : (
                    <Eye className="size-5" />
                )}
            </button>
        </InputGroup>
    );
}
