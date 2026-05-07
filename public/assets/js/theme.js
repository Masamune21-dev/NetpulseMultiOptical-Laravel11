document.addEventListener('DOMContentLoaded', () => {
    fetch('api/settings')
        .then(r => r.json())
        .then(d => {
            const theme = d.theme || 'light';
            document.documentElement.dataset.theme = theme;
            document.body.dataset.theme = theme;
            const primary = d.primary_color || '#ffe14a';
            const soft = d.primary_soft || '#ff5c8a';
            document.documentElement.style.setProperty('--primary', primary);
            document.documentElement.style.setProperty('--primary-soft', soft);
            document.documentElement.style.setProperty(
                '--primary-gradient',
                `linear-gradient(135deg, ${primary} 0%, ${soft} 100%)`
            );
            try {
                localStorage.setItem('theme', theme);
            } catch (e) { }
        })
        .catch(() => {
            document.documentElement.dataset.theme = 'light';
            document.body.dataset.theme = 'light';
        });
});
