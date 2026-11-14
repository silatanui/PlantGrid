# PlantGrid

PlantGrid is a demonstration platform for manufacturing ICT operations: assets, helpdesk, network and server records, production support, maintenance, security, backups, simulated monitoring and reports.

The sample organisation is **Northridge Processing**, a fictional plant. Monitoring results and backup runs are simulated. The application does not control factory equipment or scan a live network.

## Independent portfolio project

PlantGrid is an original educational / portfolio project by Tanui Sila Kipngetich. It is **not affiliated with, endorsed by, or a product of Salesforce or any other CRM vendor**. The product name, logo, colour system, and layout are PlantGrid’s own. Concepts such as cases, assets, and role-based consoles are general software ideas; this codebase was written from scratch for demonstration purposes and is not a commercial clone or alternative product.

## Local address

http://localhost/PlantGrid

The Laravel source lives in `C:\xampp\apps\PlantGrid`. Apache serves only `C:\xampp\htdocs\PlantGrid`.

This machine has PHP 8.2, so the application uses Laravel 12. Laravel 13 needs PHP 8.3 or newer.

## Demonstration sign-in

Password for every account: `PlantGrid-Demo`

Preferred starting account:

- amina.okello@northridge.example - System Administrator

Other roles:

- daniel.mwangi@northridge.example - ICT Manager
- faith.cheruiyot@northridge.example - ICT Technician
- peter.kamau@northridge.example - Department Manager
- grace.wanjiku@northridge.example - General Employee
- helen.achieng@northridge.example - Auditor

Grace Wanjiku has an open high-priority ticket, PG-2026-0148, for the packaging barcode label printer. Daniel can assign it and Faith can record the resolution.

## Database

Local database: `plantgrid_db` on the XAMPP MariaDB server, user `root` with an empty password.

```
C:\xampp\php\php.exe artisan migrate --seed
```

## Frontend

Outfit, Bootstrap 5, Chart.js and Lucide are bundled with Vite. The production bundle is built for the `/PlantGrid/` path and written straight to `PLANTGRID_PUBLIC_PATH` (`C:\xampp\htdocs\PlantGrid`) when that env value is set.

```
npm run build
```

Brand images belong in the htdocs `images` folder (and can be mirrored under `public\images` in the app tree).

Typography uses Outfit with a PlantGrid industrial teal palette for the manufacturing ICT console look.
