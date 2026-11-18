/**
 * The text of a tab: the title of the page and then the brand ("Empleados - Humetría"). A title that already starts with
 * the brand (the home's) is the whole title. The same rule is in app/Http/DocumentTitle.php, for the titles PHP prints.
 */
export function documentTitle(title: string, brand: string): string {
    if (title === '') {
        return brand;
    }

    return title.startsWith(brand) ? title : `${title} - ${brand}`;
}
