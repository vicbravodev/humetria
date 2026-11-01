/**
 * Where a workplace's field goes in the form and where its error comes back. Registering an aspirant sends a list
 * (`workplaces[1][street]`, error `workplaces.1.street`); adding one more sends the bare field (`street`).
 */
export function workplaceField(
    index: number | null,
    field: string,
): { name: string; error: string } {
    return index === null
        ? { name: field, error: field }
        : {
              name: `workplaces[${index}][${field}]`,
              error: `workplaces.${index}.${field}`,
          };
}
