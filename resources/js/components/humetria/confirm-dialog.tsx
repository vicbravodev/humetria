import { Form } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import { useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { RouteFormDefinition } from '@/wayfinder';

/** It sends a form (`form`) or runs something on the page (`onConfirm`). */
type Confirm =
    | {
          form: RouteFormDefinition<'post'>;
          /** What the form sends, when it is not empty. */
          transform?: NonNullable<ComponentProps<typeof Form>['transform']>;
          preserveScroll?: boolean;
          onConfirm?: never;
      }
    | {
          onConfirm: () => void;
          form?: never;
          transform?: never;
          preserveScroll?: never;
      };

/** It opens from its own button (`trigger`) or from whoever holds `open`. */
type Opening =
    | { trigger: ReactNode; open?: never; onOpenChange?: never }
    | {
          open: boolean;
          onOpenChange: (open: boolean) => void;
          trigger?: never;
      };

type Props = Confirm &
    Opening & {
        title: ReactNode;
        description: ReactNode;
        confirmLabel: string;
        /** "Volver" when "Cancelar" would read as the action itself (cancelling an invitation). */
        dismissLabel?: string;
        /** Off for a question that undoes nothing, such as sending again. */
        destructive?: boolean;
        disabled?: boolean;
        /** What else to know before answering, between the question and the buttons. */
        children?: ReactNode;
    };

/**
 * Asks before an action that cannot be undone: the question, what follows from it, and the two answers. A form
 * error (an evaluation in progress keeps its work center) shows above the buttons.
 */
export default function ConfirmDialog(props: Props) {
    const [ownOpen, setOwnOpen] = useState(false);
    const open = props.open ?? ownOpen;
    const setOpen = props.onOpenChange ?? setOwnOpen;
    const confirm =
        props.form === undefined
            ? () => {
                  setOpen(false);
                  props.onConfirm();
              }
            : undefined;
    // The two answers: keep things as they are, or go ahead (submitting the form, when there is one).
    const answers = (busy: boolean) => (
        <DialogFooter className="gap-2">
            <DialogClose asChild>
                <Button variant="outline">
                    {props.dismissLabel ?? 'Cancelar'}
                </Button>
            </DialogClose>
            <Button
                type={confirm === undefined ? 'submit' : 'button'}
                variant={
                    props.destructive === false ? 'default' : 'destructive'
                }
                disabled={busy || props.disabled === true}
                onClick={confirm}
            >
                {busy && <Spinner />}
                {props.confirmLabel}
            </Button>
        </DialogFooter>
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            {props.trigger !== undefined && (
                <DialogTrigger asChild>{props.trigger}</DialogTrigger>
            )}
            <DialogContent>
                <DialogTitle>{props.title}</DialogTitle>
                <DialogDescription>{props.description}</DialogDescription>
                {props.children}
                {props.form === undefined ? (
                    answers(false)
                ) : (
                    <Form
                        {...props.form}
                        transform={props.transform ?? ((data) => data)}
                        options={{
                            preserveScroll: props.preserveScroll ?? false,
                        }}
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                {Object.values(errors)[0] !== undefined && (
                                    <Alert
                                        variant="destructive"
                                        className="mb-4"
                                    >
                                        <AlertDescription>
                                            {Object.values(errors)[0]}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {answers(processing)}
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
