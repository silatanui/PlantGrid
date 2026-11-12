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

const base = process.env.VITE_BASE || envValue('VITE_BASE') || '/PlantGrid/build/';

export default defineConfig({
    // Local XAMPP serves assets from PLANTGRID_PUBLIC_PATH (htdocs/PlantGrid).
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
