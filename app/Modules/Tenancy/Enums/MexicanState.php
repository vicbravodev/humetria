<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Enums;

/**
 * The 32 federal entities of Mexico, stored by their ISO 3166-2:MX code.
 */
enum MexicanState: string
{
    case Aguascalientes = 'AGU';
    case BajaCalifornia = 'BCN';
    case BajaCaliforniaSur = 'BCS';
    case Campeche = 'CAM';
    case Chiapas = 'CHP';
    case Chihuahua = 'CHH';
    case MexicoCity = 'CMX';
    case Coahuila = 'COA';
    case Colima = 'COL';
    case Durango = 'DUR';
    case Guanajuato = 'GUA';
    case Guerrero = 'GRO';
    case Hidalgo = 'HID';
    case Jalisco = 'JAL';
    case StateOfMexico = 'MEX';
    case Michoacan = 'MIC';
    case Morelos = 'MOR';
    case Nayarit = 'NAY';
    case NuevoLeon = 'NLE';
    case Oaxaca = 'OAX';
    case Puebla = 'PUE';
    case Queretaro = 'QUE';
    case QuintanaRoo = 'ROO';
    case SanLuisPotosi = 'SLP';
    case Sinaloa = 'SIN';
    case Sonora = 'SON';
    case Tabasco = 'TAB';
    case Tamaulipas = 'TAM';
    case Tlaxcala = 'TLA';
    case Veracruz = 'VER';
    case Yucatan = 'YUC';
    case Zacatecas = 'ZAC';

    public function label(): string
    {
        return match ($this) {
            self::Aguascalientes => 'Aguascalientes',
            self::BajaCalifornia => 'Baja California',
            self::BajaCaliforniaSur => 'Baja California Sur',
            self::Campeche => 'Campeche',
            self::Chiapas => 'Chiapas',
            self::Chihuahua => 'Chihuahua',
            self::MexicoCity => 'Ciudad de México',
            self::Coahuila => 'Coahuila',
            self::Colima => 'Colima',
            self::Durango => 'Durango',
            self::Guanajuato => 'Guanajuato',
            self::Guerrero => 'Guerrero',
            self::Hidalgo => 'Hidalgo',
            self::Jalisco => 'Jalisco',
            self::StateOfMexico => 'Estado de México',
            self::Michoacan => 'Michoacán',
            self::Morelos => 'Morelos',
            self::Nayarit => 'Nayarit',
            self::NuevoLeon => 'Nuevo León',
            self::Oaxaca => 'Oaxaca',
            self::Puebla => 'Puebla',
            self::Queretaro => 'Querétaro',
            self::QuintanaRoo => 'Quintana Roo',
            self::SanLuisPotosi => 'San Luis Potosí',
            self::Sinaloa => 'Sinaloa',
            self::Sonora => 'Sonora',
            self::Tabasco => 'Tabasco',
            self::Tamaulipas => 'Tamaulipas',
            self::Tlaxcala => 'Tlaxcala',
            self::Veracruz => 'Veracruz',
            self::Yucatan => 'Yucatán',
            self::Zacatecas => 'Zacatecas',
        };
    }
}
