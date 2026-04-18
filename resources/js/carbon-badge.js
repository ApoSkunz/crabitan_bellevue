/**
 * Badge Website Carbon — charge le score carbone de la page courante.
 * Utilise un cache localStorage de 24h pour éviter les requêtes répétées.
 */

const BADGE_CSS = `<style>.carbonbadge{--b2:#c9a84c;font-size:13px;line-height:1.15;text-align:center}
.carbonbadge a,.carbonbadge p{text-align:center;display:inline-flex;justify-content:center;align-items:center;
font-size:1em;margin:.2em 0;line-height:1.15;font-family:system-ui,sans-serif}
#wcb_g,.carbonbadge a{padding:.3em .5em;color:#2a2218;background:#f5f0e8;border:.125rem solid var(--b2);border-radius:.3em 0 0 .3em}
#wcb_g{border-right:0;min-width:8.2em}.carbonbadge a{border-radius:0 .3em .3em 0;border-left:0;
background:#080808;color:#f5f0e8;text-decoration:none;font-weight:700;border-color:var(--b2)}</style>`;

const BADGE_HTML = '<div id="wcb_p"><p id="wcb_g">Mesure CO<sub>2</sub>&hellip;</p>'
    + '<a target="_blank" rel="noopener" href="https://websitecarbon.com">Website Carbon</a></div>'
    + '<p id="wcb_2"></p>';

export function initCarbonBadge() {
    const wcb = document.getElementById('wcb');
    if (!('fetch' in globalThis) || !wcb) return;

    // Exclusion back-office : aucun audit carbone sur les pages /admin
    // (défense en profondeur, le footer badge n'est déjà pas rendu en admin)
    const pathname = globalThis.location.pathname || '/';
    if (pathname.startsWith('/admin')) return;

    // Désactivé en local — l'API ne peut pas atteindre crabitan.local → 503 console
    const hostname = globalThis.location.hostname || '';
    if (hostname === 'localhost' || hostname === 'crabitan.local' || hostname === '127.0.0.1') return;

    // Audit page-par-page : on passe l'URL exacte de la page courante à l'API.
    // Le crawler backend WebsiteCarbon (HeadlessChrome) est whitelisté dans public/index.php
    // pour bypasser l'age-gate, donc les URLs profondes sont mesurables.
    const origin = globalThis.location.origin || 'https://www.crabitanbellevue.fr';
    const pageUrl = origin + pathname;
    const url = encodeURIComponent(pageUrl);

    function renderResult(data) {
        // Caster en nombre pour neutraliser tout contenu non numérique de l'API (XSS)
        const co2 = Number.parseFloat(data.c) || 0;
        const pct = Number.parseFloat(data.p) || 0;

        const g = document.getElementById('wcb_g');
        if (g) {
            g.textContent = '';
            g.appendChild(document.createTextNode(co2 + 'g de CO'));
            const sub = document.createElement('sub');
            sub.textContent = '2';
            g.appendChild(sub);
            g.appendChild(document.createTextNode('/vue'));
        }

        const p = document.getElementById('wcb_2');
        if (p) p.textContent += 'Plus propre que ' + pct + '% des pages testées';
    }

    function fetchBadge(render = true) {
        fetch('https://api.websitecarbon.com/b?url=' + url)
            .then((r) => { if (!r.ok) { throw new Error(r.status + ' ' + r.statusText); } return r.json(); })
            .then((data) => {
                if (render) renderResult(data);
                data.t = Date.now();
                localStorage.setItem('wcb_' + url, JSON.stringify(data));
            })
            .catch(() => {
                const g = document.getElementById('wcb_g');
                if (g) g.textContent = 'No result';
                localStorage.removeItem('wcb_' + url);
            });
    }

    wcb.insertAdjacentHTML('beforeEnd', BADGE_CSS);
    wcb.insertAdjacentHTML('beforeEnd', BADGE_HTML);

    const stored = localStorage.getItem('wcb_' + url);
    if (stored) {
        const cached = JSON.parse(stored);
        renderResult(cached);
        // Rafraîchir en arrière-plan si le cache dépasse 24h
        if (Date.now() - cached.t > 864e5) fetchBadge(false);
    } else {
        fetchBadge();
    }
}
