<?php

namespace App\Support;

enum AdmissionFormSection: string
{
    case NacionalidadDocumento = 'nacionalidad_documento';
    case LugarNacimientoDireccion = 'lugar_nacimiento_direccion';
    case EstadoCivil = 'estado_civil';
    case Familiares = 'familiares';
    case ContactosEmergencia = 'contactos_emergencia';
    case Seguro = 'seguro';
    case SalaHabitacion = 'sala_habitacion';
    case MedicoResponsable = 'medico_responsable';
    case ReferidoPor = 'referido_por';
    case Maternidad = 'maternidad';
    case OtrasHospitalizaciones = 'otras_hospitalizaciones';

    public function label(): string
    {
        return match ($this) {
            self::NacionalidadDocumento => 'Nacionalidad y documento',
            self::LugarNacimientoDireccion => 'Lugar de nacimiento y dirección',
            self::EstadoCivil => 'Estado civil',
            self::Familiares => 'Datos de padre y madre',
            self::ContactosEmergencia => 'Contactos de emergencia',
            self::Seguro => 'Seguro médico',
            self::SalaHabitacion => 'Sala y habitación',
            self::MedicoResponsable => 'Médico responsable',
            self::ReferidoPor => 'Referido por',
            self::Maternidad => 'Datos de maternidad',
            self::OtrasHospitalizaciones => 'Otras hospitalizaciones',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
