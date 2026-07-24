document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('details').forEach((details) => {
        details.addEventListener('toggle', () => {
            if (!details.open) return;

            const siblings = details.parentElement.querySelectorAll(':scope > details');

            siblings.forEach((sibling) => {
                if (sibling !== details) {
                    sibling.removeAttribute('open');
                }
            });
        });
    });
});
