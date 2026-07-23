#!/bin/bash
set -e

# Build all runtime caches (config, events, routes, views, plus Filament panel
# components and Blade icons via the registered `filament:optimize` task)
# BEFORE Octane starts, so every RoadRunner worker boots fully cached.
php artisan optimize

touch storage/logs/laravel.log
tail -f storage/logs/laravel.log &

exec php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=8000 --workers=2 --max-requests=500
