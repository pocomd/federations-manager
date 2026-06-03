# Deployment Checklist

Run these commands on the server after each release:

```bash
git pull
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan app:deploy
```

`app:deploy` clears all stale caches first, then runs migrations, seeds
roles/federations/scheduler settings, rebuilds all caches, links storage,
and warns about any `.env` keys that are present in `.env.example` but
missing from the server's `.env`.

## After adding new env variables

When `.env.example` gains new keys, add them to the server's `.env` manually
before running `app:deploy`. The deploy command will warn about any that are
still missing, but will not abort — defaults may silently hide features
(e.g. `JAGGER_IMPORT_ENABLED=false` keeps the import UI hidden until set to `true`).
