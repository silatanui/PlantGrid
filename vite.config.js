import fs from 'fs';
import path from 'path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

function envValue(name) {
    try {
        const envPath = path.resolve(process.cwd(), '.env');
        const match = fs.readFileSync(envPath, 'utf8').match(new RegExp(`^${name}=(.*)$`, 'm'));
        if (!match) {
            return null;
        }

        return match[1].trim().replace(/^["']|["']$/g, '');
    } catch (_error) {
        return null;
    }
}

const publicDirectory = (() => {
    const configured = process.env.PLANTGRID_PUBLIC_PATH || envValue('PLANTGRID_PUBLIC_PATH');
    if (configured && fs.existsSync(configured)) {
        return configured;
    }

    return 'public';
})();

// Relative URLs keep one build working on localhost/PlantGrid and the
// production subdomain root (absolute /PlantGrid/... fonts 404 online).
// Read from .env only so a stale shell VITE_BASE cannot break production builds.
const base = envValue('VITE_BASE') || './';

export default defineConfig({
    // Optional local override in .env: VITE_BASE=/PlantGrid/build/
    base,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            publicDirectory,
            buildDirectory: 'build',
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
