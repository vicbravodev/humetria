import { globalConfig } from 'zod/v4/core';
import { describe, expect, it } from 'vite-plus/test';
import {
    DefaultChatTransport,
    getToolName,
    isToolUIPart,
    useChat,
} from '@/lib/assistant/ai-client';

describe('ai-client', () => {
    it('tells zod not to evaluate code before the SDK builds its validators', () => {
        expect(globalConfig.jitless).toBe(true);
    });

    it('gives what the chat needs from the SDK', () => {
        expect(typeof useChat).toBe('function');
        expect(typeof DefaultChatTransport).toBe('function');
        expect(typeof getToolName).toBe('function');
        expect(typeof isToolUIPart).toBe('function');
    });
});
