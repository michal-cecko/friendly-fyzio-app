# FriendlyFyzio

Booking, CMS and CRM platform for a physiotherapy clinic.

> **Status: early development.** Project scaffolding + infrastructure are in place; domain models in progress. See [cecko.dev / FriendlyFyzio](https://cecko.dev/en/work) for the planned product.

## Planned scope

- **Online booking** — patients pick a therapist + slot, deposit via card, confirmation email
- **Multirole admin** — receptionist, therapist, and clinic-owner views in one Filament panel
- **Patient CRM** — visit history, notes (medically-confidential, role-gated), tags
- **CMS** — public marketing pages, blog
- **Email/SMS notifications** — appointment reminders, follow-ups

## Stack

| Layer | Tech |
|---|---|
| Backend | **Laravel 13** on PHP 8.3, **Octane** + RoadRunner |
| Admin | **Filament v5** + Shield (RBAC), Apex Charts, Impersonate |
| Media | Ralph J Smit's Filament Media Library |
| Tags | Spatie Laravel Tags |
| Errors | Sentry |
| Tests | PHPUnit |
| Build | Vite (Tailwind 4) |
| Deploy | Docker → Dokploy |

## Local dev

```bash
cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail composer install
vendor/bin/sail npm install
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
vendor/bin/sail npm run dev
```

### Composer auth

Requires Filament Pro + Ralph J Smit (`satis.ralphjsmit.com`) licenses. Provide both in a local `auth.json` (gitignored) and as a `COMPOSER_AUTH` build arg in Dokploy:

```json
{
  "http-basic": {
    "packages.filamentphp.com": { "username": "<email>", "password": "<token>" },
    "satis.ralphjsmit.com":     { "username": "<email>", "password": "<token>" }
  }
}
```

## Deploy

Two-stage `Dockerfile` (build stage uses `friendly-fyzio-app-base` for cached PHP deps; runtime stage is lean PHP 8.4 alpine with Octane). Deployed to Dokploy.

## License

[MIT](LICENSE) © Michal Čečko
