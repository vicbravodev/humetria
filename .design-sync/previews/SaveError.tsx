import { SaveError } from 'humetria';

export const CouldNotSave = () => <SaveError onRetry={() => {}} processing={false} />;

export const Retrying = () => <SaveError onRetry={() => {}} processing />;
