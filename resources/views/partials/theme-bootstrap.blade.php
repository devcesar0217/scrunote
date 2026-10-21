<script>
    (() => {
        try {
            const theme = window.localStorage.getItem('scrunote.theme');
            if (theme === 'dark' || theme === 'light') {
                document.documentElement.dataset.theme = theme;
            }
        } catch (_) {
            // Privacy modes can deny storage; dark remains the safe default.
        }
    })();
</script>
