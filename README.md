# xaCare

Sistema de gestión hospitalaria y quirúrgica open-source, desarrollado por [XACode](https://github.com/XACodev).

## Qué es xaCare

xaCare es una plataforma para hospitales, clínicas y centros de salud que permite gestionar:

- **Ingreso de pacientes** con decisión de ruta (consulta, observación o cirugía)
- **Casos quirúrgicos** con asignación de equipo completo (cirujano, ayudante, anestesiólogo, instrumentista, circulante)
- **Llenado distribuido** donde cada profesional completa sus datos desde su propio dashboard
- **Control de pagos** con cálculo automático por procedimiento realizado
- **Historial y reportes** de cirugías por fecha, profesional y tipo

## Modelo Open-Core

xaCare sigue un modelo **open-core**:

| Comunidad (este repo, AGPL-3.0) | Pro (licencia comercial) |
|---|---|
| Gestión de pacientes | Multi-tenancy (hospital = tenant) |
| Casos quirúrgicos y asignaciones | Reportes avanzados / BI |
| Roles y permisos | Facturación electrónica |
| Dashboard por profesional | Integraciones (HL7/FHIR) |
| Cálculo de pagos | White-label y personalización |
| Reportes básicos | Soporte prioritario |

## Stack

- **Backend:** Laravel 12 + Livewire / Volt
- **Frontend:** Tailwind CSS 4 + Blade
- **Auth:** Laravel Fortify + 2FA
- **Permisos:** Spatie Laravel Permission
- **Base de datos:** SQLite (dev) / MySQL o PostgreSQL (prod)

## Instalación

```bash
git clone https://github.com/XACodev/xaCare.git
cd xaCare
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

Configura tu organización en `.env`:

```env
QXLOG_ORG_NAME="Tu Hospital"
QXLOG_VOUCHER_LEGEND="Descripción para vouchers de pago"
QXLOG_DEFAULT_RATE=200.00
```

## Contribuir

Las contribuciones son bienvenidas. Si quieres colaborar:

1. Fork el repositorio
2. Crea tu rama (`git checkout -b feature/mi-feature`)
3. Haz commit de tus cambios
4. Push a tu rama y abre un Pull Request

## Licencia

Este proyecto es software de código abierto licenciado bajo la [GNU Affero General Public License v3.0 (AGPL-3.0)](https://www.gnu.org/licenses/agpl-3.0.html).

Copyright (C) 2026 [XACode](https://github.com/XACodev)
