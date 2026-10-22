<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Project Manual

For the operational guide, user workflow, and system flow for this FIRE-PMIS project, see [PROJECT_MANUAL.md](./PROJECT_MANUAL.md).

## Deploying to Render

This repository includes a Render Blueprint (`render.yaml`) and Docker configuration for a Laravel web service with a Render PostgreSQL database. On startup, the web service runs Laravel migrations against that database.

1. Generate a Laravel application key locally with `php artisan key:generate --show`. Copy the complete `base64:...` value; do not commit it.
2. In Render, create a Blueprint and select this GitHub repository and the `main` branch.
3. When Render prompts for `APP_KEY`, provide a Laravel key generated locally with `php artisan key:generate --show`. Keep it private.
4. Create/sync the Blueprint resources and wait for the first deploy to finish. The Blueprint supplies the Render database connection automatically. It may create a new web service and database alongside your existing Cloud SQL-connected service.
5. Open the new `bfp-capstone` web service URL and register the first user. If reusing an existing service, remove old Cloud SQL variables (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_SSLMODE`) so they do not confuse future configuration.

The Blueprint explicitly sets the Docker start command to `/usr/local/bin/render-entrypoint`, so a stale dashboard command override should be replaced when the Blueprint is synced. If the existing service was created with Render's Node runtime instead of Docker, create a new web service from this Blueprint; its deploy log should show a Docker image build, not a Node/Vite native build.

The Laravel app uses the Render PostgreSQL database and Eloquent; it does not use Firebase Data Connect. The Firebase Data Connect schema is a separate setup and is not required for this deployment. Render free PostgreSQL databases expire after 30 days, so do not use the free database for important long-term data.

The web service's local filesystem (including uploaded documents and trained risk models) is not durable across restarts or deploys. Do not rely on local storage for important files; configure persistent or object storage for production.

## Firebase Data Connect schema

The `dataconnect/` directory contains an initial Firebase Data Connect schema based on the BFP domain migrations. It is deployed to the Firebase project’s separate Cloud SQL database, but the Laravel Render app does not use it and no XAMPP MySQL data has been imported there.

After reviewing the schema, install the Firebase CLI, authenticate with the Google account that owns the Firebase project, then run:

```powershell
firebase deploy --only dataconnect:bfpcapstone-42f74-service:schema
```

Review the schema migration plan before accepting it. The Laravel-to-Data-Connect integration and transfer of existing XAMPP MySQL records are separate follow-up work.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
