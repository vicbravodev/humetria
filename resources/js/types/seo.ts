/** The tags of the head of a public page, as App\Http\PageSeo builds them: the one source of the server and the client. */
export type Seo = {
    title: string;
    description: string;
    canonical: string;
    image: { url: string; width: number; height: number; alt: string };
    locale: string;
    siteName: string;
    /** A JSON string already escaped by the server so it cannot close its own tag. */
    jsonLd: string;
};
