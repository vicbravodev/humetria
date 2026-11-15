import '@/components/first-steps/first-steps-tour.css';
import { useFirstStepsTour } from '@/hooks/use-first-steps-tour';

/** Runs the tour a step asked for once its page is open; it renders nothing. */
export default function FirstStepsTour() {
    useFirstStepsTour();

    return null;
}
