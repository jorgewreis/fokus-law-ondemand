# Fokus Law

Greenfield Laravel application for the synthetic MVP. This repository contains no institutional or production data.

## Stack

- Laravel 13
- PHP 8.5
- MySQL 8.4 with InnoDB
- Blade-rendered monolith

## Local setup

1. Use PHP 8.5 with Laravel-required extensions and Composer 2.
2. Run `composer install` in `apps/law`.
3. Copy `.env.example` to `.env` and set a dedicated local synthetic MySQL database and credentials.
4. Run `php artisan key:generate` and `php artisan migrate` only against that synthetic database.
5. Start local development with `php artisan serve`.

## Synthetic fixture

`php artisan db:seed` creates one unverified synthetic test account using the reserved `.test` email domain. The seeder only accepts SQLite `:memory:` while `APP_ENV=testing`, or MySQL on `localhost`/ `127.0.0.1` with database `fokus_law_synthetic` while `APP_ENV=local` or `development`. Other targets are rejected before a database connection is opened. This fixture does not grant application access.

The GitHub Actions workflow validates Composer metadata and runs the Laravel test suite against the in-memory SQLite database. It does not use production secrets or connect to institutional services.

The approved synthetic MVP flow is Pessoa → Processo. Gestão de Expedições is a future phase and is outside MVP acceptance. Access controls, environment isolation, backup/restore, and the Pessoa–Processo flow remain subject to implementation and verification. This repository does not authorize real institutional data, PJe integration, institutional homologation, or production deployment.

## Project references

- [Canonical PRD](https://app.notion.com/p/41b6af4c510981e0b980eff10759823a) — current product requirements and scope.
- [ADR-0001](../../docs/adr/0001-fokus-law-greenfield-stack-and-boundaries.md) — approved stack and environment boundaries.
- [Domain dictionary](../../docs/domain/dicionario-dominio.md) — conceptual Pessoa, Processo, Unidade, and relationship rules; not an implemented schema.
- [Synthetic fixture lifecycle](../../docs/testing/ciclo-fixtures-sinteticas.md) — allowed targets, reset limits, and observed verification evidence.
- [REI-194](https://linear.app/reis-developer/issue/REI-194/vara-implementar-o-percurso-minimo-do-mvp-institucional) — MVP parent issue.
- [REI-198](https://linear.app/reis-developer/issue/REI-198/vara-inicializar-aplicacao-greenfield-e-ambiente-sintetico-isolado) — application bootstrap and synthetic environment.

## Verification

Run from this directory:

- composer validate --strict
- php artisan test --compact

The test configuration uses SQLite in memory and must not target a production or institutional database. The test suite does not prove environment isolation, authorization completeness, MySQL restore behavior, or deployment readiness.
