/** A conversation with Humi in the history: its id, the title the SDK gave it and when it last moved (an ISO instant). */
export type ConversationRow = {
    id: string;
    title: string;
    updated_at: string;
};
