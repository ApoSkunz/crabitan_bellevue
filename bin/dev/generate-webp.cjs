#!/usr/bin/env node
/**
 * Génère des variantes WebP + redimensionnées pour les images statiques.
 *
 * Usage : node bin/dev/generate-webp.js
 *
 * Dossiers traités :
 *   public/assets/images/carousel/ → variantes 800w, 1600w (JPEG + WebP)
 *   public/assets/images/gallery/  → variantes 600w, 1200w (JPEG + WebP)
 *
 * Les fichiers générés sont placés à côté des originaux avec suffixe -NNNw.
 * Ex : vignoble-ete.jpg → vignoble-ete-800w.jpg + vignoble-ete-800w.webp
 */

const sharp = require('sharp');
const fs    = require('fs');
const path  = require('path');

const ROOT = path.resolve(__dirname, '../../public/assets/images');

const JOBS = [
    {
        dir:    path.join(ROOT, 'carousel'),
        exts:   ['.jpg', '.jpeg'],
        widths: [800, 1600],
        quality: { webp: 82, jpg: 85 },
    },
    {
        dir:    path.join(ROOT, 'gallery'),
        exts:   ['.jpg', '.jpeg', '.png'],
        widths: [600, 1200],
        quality: { webp: 82, jpg: 85, png: 9 },
    },
    {
        dir:    path.join(ROOT, 'wines'),
        exts:   ['.png', '.jpg', '.jpeg'],
        widths: [400, 800],
        quality: { webp: 82, jpg: 85, png: 9 },
    },
];

async function processFile(filePath, widths, quality) {
    const ext  = path.extname(filePath).toLowerCase();
    const base = filePath.slice(0, -ext.length);
    const meta = await sharp(filePath).metadata();

    for (const w of widths) {
        if (meta.width && meta.width < w) continue; // ne pas upscaler

        const resized = sharp(filePath).resize({ width: w, withoutEnlargement: true });

        // Variante WebP
        const webpOut = `${base}-${w}w.webp`;
        if (!fs.existsSync(webpOut)) {
            await resized.clone().webp({ quality: quality.webp }).toFile(webpOut);
            console.log(`  ✓ ${path.basename(webpOut)}`);
        }

        // Variante format original redimensionné
        let origOut;
        if (ext === '.png') {
            origOut = `${base}-${w}w.png`;
            if (!fs.existsSync(origOut)) {
                await resized.clone().png({ compressionLevel: quality.png }).toFile(origOut);
                console.log(`  ✓ ${path.basename(origOut)}`);
            }
        } else {
            origOut = `${base}-${w}w.jpg`;
            if (!fs.existsSync(origOut)) {
                await resized.clone().jpeg({ quality: quality.jpg, mozjpeg: true }).toFile(origOut);
                console.log(`  ✓ ${path.basename(origOut)}`);
            }
        }
    }
}

async function main() {
    let total = 0;

    for (const job of JOBS) {
        const files = fs.readdirSync(job.dir).filter(f => {
            const e = path.extname(f).toLowerCase();
            // Ignorer les fichiers déjà générés (suffixe -NNNw)
            return job.exts.includes(e) && !/-\d+w\.(webp|jpg|jpeg|png)$/.test(f);
        });

        console.log(`\n📁 ${path.relative(ROOT, job.dir)} — ${files.length} fichier(s)`);

        for (const f of files) {
            const filePath = path.join(job.dir, f);
            console.log(`  → ${f}`);
            await processFile(filePath, job.widths, job.quality);
            total++;
        }
    }

    console.log(`\n✅ ${total} image(s) traitée(s).`);
}

main().catch(err => {
    console.error('Erreur :', err.message);
    process.exit(1);
});
