# PlantGrid

PlantGrid is a demonstration platform for manufacturing ICT operations: assets, helpdesk, network and server records, production support, maintenance, security, backups, simulated monitoring and reports.

The sample organisation is **Northridge Processing**, a fictional plant. Monitoring results and backup runs are simulated. The application does not control factory equipment or scan a live network.

## Local address

http://localhost/PlantGrid

The Laravel source lives in `C:\xampp\apps\PlantGrid`. Apache serves only `C:\xampp\htdocs\PlantGrid`.

This machine has PHP 8.2, so the application uses Laravel 12. Laravel 13 needs PHP 8.3 or newer.

## Demonstration sign-in

Password for every account: `PlantGrid-Demo`

- amina.okello@northridge.example — System Administrator
- daniel.mwangi@northridge.example — ICT Manager
- faith.cheruiyot@northridge.example — ICT Technician
- peter.kamau@northridge.example — Department Manager
- grace.wanjiku@northridge.example — General Employee
- helen.achieng@northridge.example — Auditor

Grace Wanjiku has an open high-priority ticket, PG-2026-0148, for the packaging barcode label printer. Daniel can assign it and Faith can record the resolution.

## Database

Local database: `plantgrid_db` on the XAMPP MariaDB server, user `root` with an empty password.

```
C:\xampp\php\php.exe artisan migrate --seed
```

## Frontend

System UI fonts, Bootstrap 5, Chart.js and Lucide are bundled with Vite. The production bundle is built for the `/PlantGrid/` path.

```
npm run build
```

Copy `public\build` to `C:\xampp\htdocs\PlantGrid\build` after each build. The brand images belong in `public\images` and in the htdocs images folder.

Typography uses the system UI font stack for a traditional enterprise console look.
