import { apiPost, toast } from '../helpers.js';

export function init() {
    const grid = document.getElementById('appThemesGrid');
    const state = document.getElementById('appThemeSaveState');
    if (!grid) return;

    grid.querySelectorAll('.theme-card').forEach(card => {
        card.addEventListener('click', async () => {
            const theme = card.dataset.theme;
            if (card.classList.contains('is-active')) return;

            if (state) {
                state.textContent = 'Application…';
                state.className = 'small text-muted';
            }

            try {
                const r = await apiPost('save_apparence', { theme });
                if (!r.success) throw new Error(r.message || 'Erreur');

                grid.querySelectorAll('.theme-card').forEach(c => c.classList.remove('is-active'));
                card.classList.add('is-active');

                // Charger Google Fonts si on bascule sur care
                if (theme === 'care' && !document.querySelector('link[href*="Fraunces"]')) {
                    const css = document.createElement('link');
                    css.rel = 'stylesheet';
                    css.href = 'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap';
                    document.head.appendChild(css);
                }

                document.body.className = document.body.className.replace(/\btheme-\w+\b/g, '').trim();
                document.body.classList.add('theme-' + theme);

                if (state) {
                    state.textContent = '✓ Thème appliqué';
                    state.className = 'small text-success';
                    setTimeout(() => { state.textContent = ''; }, 2200);
                }
                toast('Thème appliqué', 'success');
            } catch (e) {
                if (state) {
                    state.textContent = '⚠ ' + (e.message || 'Erreur');
                    state.className = 'small text-danger';
                }
                toast('Erreur : ' + (e.message || 'Inconnue'), 'danger');
            }
        });
    });
}

export function destroy() {}
