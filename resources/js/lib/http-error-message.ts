type ErrorResponse = {
    status: number;
    data: unknown;
};

/**
 * The text to show when the server turned a request away for coming too often (429),
 * or null when the response is any other error and Inertia should handle it as usual.
 */
export function tooManyRequestsMessage(response: ErrorResponse): string | null {
    return response.status === 429 &&
        typeof response.data === 'string' &&
        response.data !== ''
        ? response.data
        : null;
}
