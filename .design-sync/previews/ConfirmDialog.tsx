import { Button, ConfirmDialog } from 'humetria';

export const CloseEvaluation = () => (
    <ConfirmDialog
        open
        onOpenChange={() => {}}
        onConfirm={() => {}}
        title="¿Cerrar la evaluación de Planta Apodaca?"
        description="Ya no se recibirán respuestas. 142 de 186 personas contestaron; los resultados se calculan con ellas."
        confirmLabel="Cerrar evaluación"
    />
);

export const NotDestructive = () => (
    <ConfirmDialog
        open
        onOpenChange={() => {}}
        onConfirm={() => {}}
        destructive={false}
        title="¿Enviar el recordatorio otra vez?"
        description="Llegará a las 44 personas que aún no contestan."
        confirmLabel="Enviar recordatorio"
    />
);

export const FromItsButton = () => (
    <ConfirmDialog
        trigger={<Button variant="destructive">Eliminar centro</Button>}
        onConfirm={() => {}}
        title="¿Eliminar el centro Planta Apodaca?"
        description="Se borran sus áreas y su historial. No se puede deshacer."
        confirmLabel="Eliminar centro"
    />
);
