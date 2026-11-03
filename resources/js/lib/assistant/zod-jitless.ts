import { config } from 'zod';

// zod builds validators by evaluating generated code, which the Content Security Policy of the panel forbids (and reports
// as a violation each time it tries). The AI SDK validates the stream with zod: this makes zod validate without eval.
config({ jitless: true });
