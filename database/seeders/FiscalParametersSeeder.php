<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The fiscal and legal values of docs/normas/compensation/criterios.md, version 1.0 (consulted 2026-11-03), and only
 * those: what the document leaves "por verificar" is not seeded (the work risk premium is per account; Sinaloa's
 * progressive payroll tax is not a rate; 2025 minimum wage and 2027 values do not exist in the document).
 *
 * It runs once, from the migration 2026_11_30_700000_seed_fiscal_parameters. A new vigencia (UMA each January, the
 * minimum wage and the payroll taxes each December) goes in a new migration with its own rows, never by editing this
 * one: the data is versioned like the schema. Money is whole cents (scale 0); a rate is value_int / 10^scale.
 */
final class FiscalParametersSeeder extends Seeder
{
    private const string CONSULTED = '2026-11-03';

    /** The vigencia of the laws whose text is "vigente" at the consult date: the start is the calculator's horizon, not the law's own date. */
    private const string HORIZON = '2023-01-01';

    private const string HORIZON_NOTE = 'Texto vigente al 2026-11-03; la fecha de inicio es el límite inferior del horizonte de la calculadora, no la de entrada en vigor de la ley.';

    private const string LSS = 'https://www.diputados.gob.mx/LeyesBiblio/pdf/LSS.pdf';

    private const string LFT = 'https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf';

    public function run(): void
    {
        $now = now();

        DB::table('fiscal_parameters')->insert(array_map(
            fn (array $row): array => [...$row, 'id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now],
            self::rows(),
        ));
    }

    /**
     * @return list<array{key: string, state: ?string, valid_from: string, valid_to: ?string, value_int: int, scale: int, source: string, consulted_on: string, note: ?string}>
     */
    public static function rows(): array
    {
        return [
            ...self::units(),
            ...self::contributions(),
            ...self::severanceOldAge(),
            ...self::legalMinimums(),
            ...self::payrollTaxes(),
        ];
    }

    /**
     * @return array{key: string, state: ?string, valid_from: string, valid_to: ?string, value_int: int, scale: int, source: string, consulted_on: string, note: ?string}
     */
    private static function row(string $key, string $from, ?string $to, int $value, int $scale, string $source, ?string $state = null, ?string $note = null): array
    {
        return [
            'key' => $key, 'state' => $state, 'valid_from' => $from, 'valid_to' => $to, 'value_int' => $value, 'scale' => $scale,
            'source' => $source, 'consulted_on' => self::CONSULTED, 'note' => $note,
        ];
    }

    /** @return list<array{key: string, state: ?string, valid_from: string, valid_to: ?string, value_int: int, scale: int, source: string, consulted_on: string, note: ?string}> */
    private static function units(): array
    {
        $uma = 'INEGI, aviso del valor de la UMA, DOF %s (%s)';
        $minimumWage = 'CONASAMI, Resolución de los salarios mínimos 2026, DOF 09/12/2025, considerando tercero (https://dof.gob.mx/nota_detalle_popup.php?codigo=5775534)';

        return [
            self::row('uma', '2024-02-01', '2025-01-31', 10857, 0, sprintf($uma, '10/01/2024', 'https://sc.inegi.org.mx/repositorioNormateca/UMA_24.pdf'), note: 'UMA diaria $108.57.'),
            self::row('uma', '2025-02-01', '2026-01-31', 11314, 0, sprintf($uma, '10/01/2025', 'https://dof.gob.mx/nota_detalle_popup.php?codigo=5746930'), note: 'UMA diaria $113.14.'),
            self::row('uma', '2026-02-01', '2027-01-31', 11731, 0, sprintf($uma, '09/01/2026', 'https://dof.gob.mx/nota_detalle_popup.php?codigo=5778072'), note: 'UMA diaria $117.31. La del año siguiente se publica en enero de 2027.'),
            self::row('minimum_wage', '2026-01-01', '2026-12-31', 31504, 0, $minimumWage, note: 'Zona del Salario Mínimo General, $315.04 diarios. El de 2027 se publica en diciembre de 2026.'),
            self::row('minimum_wage_border', '2026-01-01', '2026-12-31', 44087, 0, $minimumWage, note: 'Zona Libre de la Frontera Norte, $440.87 diarios.'),
        ];
    }

    /** @return list<array{key: string, state: ?string, valid_from: string, valid_to: ?string, value_int: int, scale: int, source: string, consulted_on: string, note: ?string}> */
    private static function contributions(): array
    {
        $fixedFrom = '2007-07-01';
        $fixedNote = 'Fracción de la UMA diaria por persona asegurada, no del SBC.';

        return [
            self::row('imss_fixed', $fixedFrom, null, 2040, 4, 'LSS art. 106 fr. I y trans. 19.º de la ley de 1995 (13.9 % + 10 × 0.65); IMSS, Informe 2022-2023, cuadro A.3 ('.self::LSS.')', note: $fixedNote),
            self::row('imss_surplus', $fixedFrom, null, 110, 4, 'LSS art. 106 fr. II y trans. 19.º (6 − 10 × 0.49); IMSS, Informe 2022-2023, cuadro A.3 ('.self::LSS.')', note: 'Sobre (SBC − 3 UMA), solo si el SBC supera 3 UMA.'),
            self::row('imss_surplus_umas', $fixedFrom, null, 3, 0, 'LSS art. 106 fr. II ('.self::LSS.')', note: 'Veces la UMA diaria desde las que se paga la cuota excedente.'),
            self::row('imss_pensioners', self::HORIZON, null, 105, 4, 'LSS art. 25 párr. 2.º ('.self::LSS.')', note: self::HORIZON_NOTE),
            self::row('imss_cash', self::HORIZON, null, 70, 4, 'LSS art. 107 fr. I (70 % de 1 %) ('.self::LSS.')', note: self::HORIZON_NOTE),
            self::row('imss_disability_life', self::HORIZON, null, 175, 4, 'LSS art. 147 ('.self::LSS.')', note: self::HORIZON_NOTE),
            self::row('imss_childcare', self::HORIZON, null, 100, 4, 'LSS arts. 211-212 ('.self::LSS.')', note: self::HORIZON_NOTE),
            self::row('retirement', self::HORIZON, null, 200, 4, 'LSS art. 168 fr. I ('.self::LSS.')', note: self::HORIZON_NOTE),
            self::row('infonavit', self::HORIZON, null, 500, 4, 'Ley del Infonavit art. 29 fr. II (https://www.diputados.gob.mx/LeyesBiblio/pdf/LIFNVT.pdf)', note: self::HORIZON_NOTE.' Mismo tope de 25 UMA que el SBC.'),
        ];
    }

    /**
     * Cesantía en edad avanzada y vejez, employer, % of the SBC by range and year. The upper limit of ranges 2 to 7 is in
     * UMA ("severance_old_age_limit.n"); range 1 is the minimum wage and range 8 has no limit. 2030 is fixed afterwards.
     *
     * @return list<array{key: string, state: ?string, valid_from: string, valid_to: ?string, value_int: int, scale: int, source: string, consulted_on: string, note: ?string}>
     */
    private static function severanceOldAge(): array
    {
        $source = 'Decreto DOF 16/12/2020 (reforma de pensiones), trans. 2.º, p. 32; LSS art. 168 fr. II a) (https://www.diputados.gob.mx/LeyesBiblio/ref/lss/LSS_ref25_16dic20.pdf)';
        // Percent × 1000 by range (1 to 8), for 2023 to 2030.
        $table = [
            2023 => [3150, 3281, 3575, 3751, 3869, 3953, 4016, 4241],
            2024 => [3150, 3413, 4000, 4353, 4588, 4756, 4882, 5331],
            2025 => [3150, 3544, 4426, 4954, 5307, 5559, 5747, 6422],
            2026 => [3150, 3676, 4851, 5556, 6026, 6361, 6613, 7513],
            2027 => [3150, 3807, 5276, 6157, 6745, 7164, 7479, 8603],
            2028 => [3150, 3939, 5701, 6759, 7464, 7967, 8345, 9694],
            2029 => [3150, 4070, 6126, 7360, 8183, 8770, 9211, 10784],
            2030 => [3150, 4202, 6552, 7962, 8902, 9573, 10077, 11875],
        ];
        $rows = [];

        foreach ($table as $year => $percents) {
            foreach ($percents as $index => $percent) {
                $rows[] = self::row('severance_old_age.'.($index + 1), "{$year}-01-01", $year === 2030 ? null : "{$year}-12-31", $percent, 5, $source, note: $year === 2030 ? 'Después de 2030 el texto del art. 168 queda fijo en esta columna.' : null);
            }
        }

        // Upper limits in UMA (×100); range 1 is "1.0 SM" and the last has no limit.
        foreach ([2 => 150, 3 => 200, 4 => 250, 5 => 300, 6 => 350, 7 => 400] as $bracket => $limit) {
            $rows[] = self::row("severance_old_age_limit.{$bracket}", '2023-01-01', null, $limit, 2, $source, note: 'Límite superior inclusivo del rango, en veces la UMA diaria.');
        }

        return $rows;
    }

    /** @return list<array{key: string, state: ?string, valid_from: string, valid_to: ?string, value_int: int, scale: int, source: string, consulted_on: string, note: ?string}> */
    private static function legalMinimums(): array
    {
        $rows = [
            self::row('christmas_bonus_days', self::HORIZON, null, 15, 0, 'LFT art. 87 ('.self::LFT.')', note: self::HORIZON_NOTE),
            self::row('vacation_premium', self::HORIZON, null, 25, 2, 'LFT art. 80 ('.self::LFT.')', note: self::HORIZON_NOTE),
            self::row('seniority_premium_days', self::HORIZON, null, 12, 0, 'LFT art. 162 fr. I ('.self::LFT.')', note: 'Días de salario por año de servicio. '.self::HORIZON_NOTE),
            self::row('seniority_premium_cap_minimum_wages', self::HORIZON, null, 2, 0, 'LFT art. 162 fr. II y arts. 485-486 ('.self::LFT.')', note: 'Salarios mínimos del área como tope del salario de la prima. '.self::HORIZON_NOTE),
            self::row('voucher_exempt_uma', self::HORIZON, null, 40, 2, 'LSS art. 27 fr. VI ('.self::LSS.')', note: 'Fracción de la UMA diaria por día hasta la que las despensas no integran el SBC. '.self::HORIZON_NOTE),
        ];

        // LFT art. 76 (reform DOF 27-12-2022): days from the given completed year of service; the document gives no table beyond 31.
        foreach ([1 => 12, 2 => 14, 3 => 16, 4 => 18, 5 => 20, 6 => 22, 11 => 24, 16 => 26, 21 => 28, 26 => 30, 31 => 32] as $year => $days) {
            $rows[] = self::row("vacation_days.{$year}", self::HORIZON, null, $days, 0, 'LFT art. 76 ('.self::LFT.')', note: 'Días laborables desde el año cumplido de servicio '.$year.'. '.self::HORIZON_NOTE);
        }

        return $rows;
    }

    /**
     * ISN of the 31 states that have a flat rate, as of 2026 (valid until 2026-12-31: "revisar cada diciembre"). The
     * note carries the base and the particularities of criterios.md section 6. Sinaloa (progressive tariff) is not seeded.
     *
     * @return list<array{key: string, state: ?string, valid_from: string, valid_to: ?string, value_int: int, scale: int, source: string, consulted_on: string, note: ?string}>
     */
    private static function payrollTaxes(): array
    {
        $rows = [];

        foreach (self::states() as [$code, $file, $basis, $rate, $note]) {
            $to = $code === 'CHH' ? '2027-12-31' : '2026-12-31';
            $rows[] = self::row('payroll_tax', '2026-01-01', $to, $rate, 4, "{$basis}; extracto y URL oficial en docs/normas/compensation/fuentes/isn/{$file}.txt", $code, $note);
        }

        $rows[] = self::row('payroll_tax', '2028-01-01', '2028-12-31', 300, 4, 'Ley de Hacienda del Estado de Chihuahua, art. 75; vuelve a 3 % al terminar el transitorio 2.º del Decreto LXVIII/RFLYC/0462/2025 (https://www.congresochihuahua2.gob.mx/biblioteca/leyes/archivosLeyes/1442.pdf)', 'CHH', 'Tasa del art. 75 una vez que termina el 4 % de 2026 y 2027.');

        return $rows;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: int, 4: ?string}> code, file, law and article, rate × 10^4, note
     */
    private static function states(): array
    {
        return [
            ['AGU', 'aguascalientes', 'Ley de Hacienda del Estado de Aguascalientes, art. 67 (base art. 66)', 250, 'Estímulo de 50 % solo para ciertos grupos (Ley de Ingresos 2026, art. 41); no se aplica.'],
            ['BCN', 'baja-california', 'Ley de Hacienda del Estado de Baja California, art. 151-16', 425, 'Excluye previsión social, PTU e indemnizaciones (art. 151-18). Por verificar: no hay consolidado posterior a dic-2023.'],
            ['BCS', 'baja-california-sur', 'Ley de Hacienda del Estado de Baja California Sur, art. 36 (reforma BOGE 31-dic-2025)', 300, 'Antes 2.5 %. Despensas hasta 40 % de la UMA diaria excluidas (art. 35).'],
            ['CAM', 'campeche', 'Ley de Hacienda del Estado de Campeche, art. 24; adicional art. 53-D', 399, '3 % más 33 % adicional sobre el impuesto = 3.99 % efectiva. La base incluye aguinaldo, despensa, fondo de ahorro y prima vacacional (art. 20).'],
            ['CHP', 'chiapas', 'Código Fiscal del Estado de Chiapas, art. 235 (reforma P.O. 9-dic-2025)', 300, 'Antes 2 %. Vales, aguinaldo, PTU y fondo de ahorro gravados sin tope (art. 229).'],
            ['CHH', 'chihuahua', 'Ley de Hacienda del Estado de Chihuahua, art. 75 (3 %) y trans. 2.º del Decreto LXVIII/RFLYC/0462/2025', 400, '4 % en 2026 y 2027. Exenta despensa en vales hasta 40 % de la UMA y fondo de ahorro igual al del trabajador (art. 77).'],
            ['CMX', 'ciudad-de-mexico', 'Código Fiscal de la Ciudad de México, art. 158 (reforma G.O. 27-dic-2024)', 400, 'El art. 278 prevé una reducción «equivalente al 1 %» por crecer la planta 33 %; es ambigua y no se aplica (por verificar).'],
            ['COA', 'coahuila', 'Ley de Hacienda del Estado de Coahuila, art. 24 (Decreto 563, 2023)', 300, 'Reducción de 20-50 % por empleo de adultos mayores y personas con discapacidad (arts. 30-31); no se aplica.'],
            ['COL', 'colima', 'Ley de Hacienda del Estado de Colima, art. 41 Q (Decreto 199, P.O. 18-dic-2025)', 300, 'Antes 2 %. Aguinaldo y prima vacacional incluidos; fondo de ahorro igual al del trabajador, excluido.'],
            ['DUR', 'durango', 'Ley de Hacienda del Estado de Durango, art. 7', 300, 'Despensa excluida hasta 40 % de la UMA. Por verificar: 2 % para hasta 10 trabajadores; se usa 3 %.'],
            ['GUA', 'guanajuato', 'Ley de Hacienda para el Estado de Guanajuato, art. 10', 300, 'Despensa gravada solo por el excedente de 40 % de la UMA diaria (art. 9 fr. XI).'],
            ['GRO', 'guerrero', 'Ley 419 de Hacienda del Estado de Guerrero, art. 44; adicionales arts. 51, 53 y 54', 300, 'Tasa nominal. Los arts. 51, 53 y 54 imponen tres adicionales de 15 % sobre el impuesto (efectiva 4.35 %, por verificar): no se incluyen y el costo puede estar subestimado.'],
            ['HID', 'hidalgo', 'Ley de Hacienda del Estado de Hidalgo, art. 24', 300, 'Despensa, alimentación y transporte excluidos en conjunto hasta 40 % del salario (art. 27).'],
            ['JAL', 'jalisco', 'Ley de Ingresos del Estado de Jalisco 2026, art. 13 (Ley de Hacienda, art. 42, remite a ella)', 300, 'Exención de 100 % el primer año y 50 % el segundo por primer empleo formal; no se aplica. Por verificar: la Ley de Hacienda compilada es de abr-2024.'],
            ['MEX', 'estado-de-mexico', 'Código Financiero del Estado de México y Municipios, art. 57', 300, 'Personas físicas con hasta 4 trabajadores: cuota de $250 por trabajador (no se aplica). Aguinaldo, fondo de ahorro y despensa gravados sin tope.'],
            ['MIC', 'michoacan', 'Ley de Hacienda del Estado de Michoacán, art. 45 (Ley de Ingresos 2026, art. 14)', 300, 'Despensa excluida hasta 40 % de la UMA; prima vacacional dudosa por la exclusión de «las vacaciones».'],
            ['MOR', 'morelos', 'Ley General de Hacienda del Estado de Morelos, art. 58 BIS-4 (Decreto 997, P.O. 22-dic-2025)', 300, 'Antes 2.5 %. Aguinaldo incluido; despensa exenta hasta 30 % del salario mínimo mensual; PTU se lee gravada.'],
            ['NAY', 'nayarit', 'Ley de Hacienda del Estado de Nayarit, art. 87', 300, 'Aguinaldo y PTU exentos.'],
            ['NLE', 'nuevo-leon', 'Ley de Hacienda del Estado de Nuevo León, art. 157', 300, 'El alza a 4 % propuesta para 2026 no se aprobó (por verificar: no se halló el P.O. de dic-2025). Aguinaldo incluido; despensas y fondo de ahorro exentos (art. 160).'],
            ['OAX', 'oaxaca', 'Ley Estatal de Hacienda de Oaxaca, art. 66 (Ley de Ingresos 2026, arts. 17-20)', 300, 'Estímulos 2026: 25 % a microempresas (1-10) y 10 % a 11-50 trabajadores; no se aplican.'],
            ['PUE', 'puebla', 'Ley de Ingresos del Estado de Puebla 2026, art. 16 (Ley de Hacienda, art. 14, remite a ella)', 300, '25 % de estímulo con hasta 4 trabajadores (no se aplica); despensa solo por monedero y hasta 40 % del salario mínimo estatal.'],
            ['QUE', 'queretaro', 'Ley de Hacienda del Estado de Querétaro, art. 72', 300, 'Se resta una deducción de 8 salarios mínimos mensuales (no se aplica); despensa hasta 40 % del salario mínimo. Por verificar: reforma P.O. 23-dic-2025 no leída.'],
            ['ROO', 'quintana-roo', 'Ley del Impuesto sobre Nóminas de Quintana Roo, art. 6', 400, 'Prima vacacional incluida; indemnizaciones y despensas «cuando se cobren al trabajador» no gravadas (art. 17).'],
            ['SLP', 'san-luis-potosi', 'Ley de Hacienda del Estado de San Luis Potosí, art. 23', 300, 'Fondo de ahorro, vales, PTU e indemnizaciones exceptuados (art. 27).'],
            ['SON', 'sonora', 'Ley de Hacienda del Estado de Sonora, art. 216; adicional art. 292 BIS-9', 300, 'Tasa base. Se suma 1 % adicional (4 %) con 100 o más trabajadores en el IMSS en Sonora, que no se modela. Exención de 1 UMA mensual por trabajador (hasta 6) con hasta 20 trabajadores (art. 218 BIS-2); no se aplica.'],
            ['TAB', 'tabasco', 'Ley de Hacienda del Estado de Tabasco, art. 30', 350, '3.0 % para poderes, órganos autónomos y municipios (no aplica a empresas). Reforma P.O. 18-dic-2024; excluye PTU, pensiones, viáticos.'],
            ['TAM', 'tamaulipas', 'Ley de Hacienda del Estado de Tamaulipas, art. 49', 300, 'Aguinaldo y vales de despensa gravados; PTU exenta hasta 15 salarios mínimos.'],
            ['TLA', 'tlaxcala', 'Código Financiero para el Estado de Tlaxcala, art. 134', 300, 'Con el incentivo de 33 % (Ley de Ingresos 2026, art. 24) queda en 2.01 %, pero exige solicitud y autorización de Finanzas; no es automático y no se aplica.'],
            ['VER', 'veracruz', 'Código Financiero para el Estado de Veracruz, art. 101', 300, 'El texto con reforma del 13-ene-2026 dice 3 %. Por verificar: Ley de Ingresos 2026 no localizada.'],
            ['YUC', 'yucatan', 'Ley General de Hacienda del Estado de Yucatán, art. 24 (Decreto 138/2025)', 375, '+1 % para poderes y organismos estatales (no aplica a empresas). Decreto 147/2025: micro y pequeñas (hasta 50 trabajadores) pagan efectiva 3 % en 2026, vigente hasta 31-ene-2027; no se aplica.'],
            ['ZAC', 'zacatecas', 'Ley de Hacienda del Estado de Zacatecas, art. 40 (Decreto 22, P.O. 28-dic-2024)', 350, 'Aguinaldo, PTU, despensa, fondo de ahorro y prima vacacional gravados expresamente.'],
        ];
    }
}
