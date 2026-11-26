Label con mensaje de error asociado a un control; reemplaza al `FormItem`/`FormLabel`/`FormMessage` de shadcn. Para un campo de texto es `FormField` (`components/form-field.tsx`: `name`, `label`, `prefix` opcional, `error`); para otros controles, `Label` de shadcn más `InputError` (`components/humetria/input-error.tsx`).

- El label va siempre visible arriba del control; nunca uses el placeholder como label.
- El error aparece debajo, en `destructive`, con ícono y texto que dice cómo corregir (“Escribe un correo válido, por ejemplo…”), no solo qué falló.
- Con error, el control recibe `aria-invalid` y un borde `destructive` de 2 px; el mensaje se enlaza con `aria-describedby`.
- Valida al salir del campo o al enviar, nunca mientras la persona escribe.
