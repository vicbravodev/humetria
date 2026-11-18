/** The identity provider of a company; the secret never comes to the page, only whether there is one. */
export type SsoConnection = {
    issuer: string;
    client_id: string;
    has_secret: boolean;
    enabled: boolean;
    enforced: boolean;
};

/** A domain the company claims, and the DNS record that proves it is theirs. */
export type SsoDomain = {
    id: string;
    domain: string;
    verified: boolean;
    record_name: string;
    record_value: string;
};
