const THEME_COLOR_VARS = [
    { key: 'primary_color',  cssVar: '--primary',      default: '#ffe14a' },
    { key: 'primary_soft',   cssVar: '--primary-soft', default: '#ff5c8a' },
    { key: 'accent_color',   cssVar: '--accent',       default: '#00d1ff' },
    { key: 'accent_2_color', cssVar: '--accent-2',     default: '#70f570' },
    { key: 'danger_color',   cssVar: '--danger',       default: '#ef4444' },
    { key: 'warning_color',  cssVar: '--warning',      default: '#f59e0b' },
];

function isHex(v) {
    return typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v.trim());
}

document.addEventListener('DOMContentLoaded', () => {
    fetch('api/settings')
        .then(r => r.json())
        .then(d => {
            const theme = d.theme || 'light';
            document.documentElement.dataset.theme = theme;
            document.body.dataset.theme = theme;

            const applied = {};
            THEME_COLOR_VARS.forEach(({ key, cssVar, default: dflt }) => {
                const value = isHex(d[key]) ? d[key].toLowerCase() : dflt;
                document.documentElement.style.setProperty(cssVar, value);
                applied[key] = value;
                try { localStorage.setItem(key, value); } catch (e) {}
            });

            // Keep --primary-gradient consistent for any leftover usage.
            document.documentElement.style.setProperty(
                '--primary-gradient',
                `linear-gradient(135deg, ${applied.primary_color} 0%, ${applied.primary_soft} 100%)`
            );

            try { localStorage.setItem('theme', theme); } catch (e) {}
        })
        .catch(() => {
            document.documentElement.dataset.theme = 'light';
            document.body.dataset.theme = 'light';
        });
});
