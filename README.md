# migrate-stack

`migrate.wexconnect.com.tr` — legacy → yeni prod veri temizlik/staging paneli için Docker zemini.

## Bileşenler
- **nginx** (80/443) — `migrate.wexconnect.com.tr`, Cloudflare Origin cert (proxied/turuncu bulut şart)
- **php-fpm 8.3** — Laravel-hazır (pdo_mysql, mbstring, gd, zip, intl, bcmath, redis, opcache) + composer
- **MySQL 8.0** — `migrate` DB, dışarı KAPALI (yalnız iç docker ağı)

## Kurulum
```bash
cp .env.example .env   # MySQL parolalarını doldur
# certs/public.crt + certs/private.key  (Cloudflare Origin cert — repoya girmez)
docker compose up -d --build
```

## Repoya girmeyenler (.gitignore)
`.env` (parolalar) ve `certs/` (SSL) — güvenli yerde tutulur, sunucuya elle konur.

## Notlar
- MySQL host'a port yayınlamaz; erişim yalnız compose iç ağı üzerinden.
- Origin'i yalnız Cloudflare IP'lerine kısıtlama: `DOCKER-USER` iptables zinciri (UFW Docker portlarını baypas eder).
