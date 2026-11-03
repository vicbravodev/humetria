import DossierSection from '@/components/disclosure/dossier-section';
import { countOf } from '@/lib/plural';
import type { DossierProps } from '@/types';

/**
 * What is recorded behind the rows of the board: the policy in force, the acknowledgments as a count, the physical means, the
 * link of the page, where each block of 5.7 comes from and the results published. Counts and days; no names.
 */
export default function DossierEvidence({ data }: { data: DossierProps }) {
    const { policy, acknowledgments, records, link, contents, results } = data;

    return (
        <>
            <DossierSection title="Política de prevención (5.1)">
                {policy === null ? (
                    <p>Sin política publicada para este centro.</p>
                ) : (
                    <p>
                        Versión {policy.version}, publicada el{' '}
                        {policy.publishedOn}. Declara los incisos{' '}
                        {policy.covers.length === 0
                            ? 'ninguno'
                            : policy.covers.join(', ')}{' '}
                        del numeral 5.1.
                    </p>
                )}
                {acknowledgments !== null && (
                    <p>
                        Acuses de lectura (no los pide la norma):{' '}
                        {acknowledgments.confirmed} de{' '}
                        {countOf(
                            acknowledgments.active,
                            'persona activa',
                            'personas activas',
                        )}
                        .
                    </p>
                )}
            </DossierSection>
            <DossierSection title="Medios físicos de difusión (10.2)">
                {records.length === 0 ? (
                    <p>Ninguno registrado.</p>
                ) : (
                    <ul className="ml-5 list-disc">
                        {records.map((record) => (
                            <li
                                key={`${record.doneOn}-${record.mediumLabel}-${record.items.join('')}`}
                            >
                                {record.doneOn}: {record.mediumLabel}, cubrió{' '}
                                {record.items.join(', ')}
                                {record.note !== null && ` (${record.note})`}.
                            </li>
                        ))}
                    </ul>
                )}
            </DossierSection>
            <DossierSection title="Página de las personas">
                {link === null ? (
                    <p>Sin enlace creado.</p>
                ) : (
                    <p>
                        Enlace {link.state.label.toLowerCase()}, creado el{' '}
                        {link.createdOn}
                        {link.expiresOn === null
                            ? ', sin vencimiento.'
                            : `, abre hasta el ${link.expiresOn}.`}
                    </p>
                )}
            </DossierSection>
            <DossierSection title="Contenidos del numeral 5.7">
                <ul className="ml-5 list-disc">
                    {contents.map((block) => (
                        <li key={block.inciso}>
                            {block.inciso}) {block.label}:{' '}
                            {block.sources.length === 0
                                ? 'sin información publicada'
                                : block.sources
                                      .map((source) =>
                                          source.publishedOn === null
                                              ? source.origin
                                              : `${source.origin} (${source.publishedOn})`,
                                      )
                                      .join('; ')}
                            .
                        </li>
                    ))}
                </ul>
            </DossierSection>
            <DossierSection title="Resultados para las personas (7.8)">
                {results === null ? (
                    <p>Ninguno publicado.</p>
                ) : (
                    <p>
                        Evaluación cerrada el {results.closedOn}, publicada el{' '}
                        {results.publishedOn}
                        {results.state === 'below_minimum'
                            ? ' (sin cifras: pocas respuestas).'
                            : '.'}
                    </p>
                )}
            </DossierSection>
        </>
    );
}
