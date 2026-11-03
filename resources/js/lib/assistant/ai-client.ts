// The AI SDK of the chat, behind a door: zod must be told not to use eval BEFORE the SDK builds its validators, and an
// import evaluates in the order it is written. Everything of the chat that needs the SDK takes it from here.
import '@/lib/assistant/zod-jitless';

export { useChat } from '@ai-sdk/react';
export { DefaultChatTransport, getToolName, isToolUIPart } from 'ai';
