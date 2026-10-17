document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;

    if (target.closest('[data-dismiss]')) {
        target.closest('.alert')?.remove();
    }

    if (target.closest('[data-menu-toggle]')) {
        document.querySelector('#sidebar')?.classList.toggle('is-open');
    } else if (!target.closest('#sidebar')) {
        document.querySelector('#sidebar')?.classList.remove('is-open');
    }
});

const themeToggle = document.querySelector('[data-theme-toggle]');
if (themeToggle instanceof HTMLButtonElement) {
    const themeLabel = themeToggle.querySelector('.theme-label');
    const updateThemeToggle = () => {
        const isDark = document.documentElement.dataset.theme === 'dark';
        const action = isDark ? 'light' : 'dark';
        themeToggle.setAttribute('aria-label', `Switch to ${action} mode`);
        themeToggle.title = `Switch to ${action} mode`;
        themeToggle.querySelector('[aria-hidden="true"]')?.replaceChildren(document.createTextNode(isDark ? '☀' : '☾'));
        if (themeLabel) themeLabel.textContent = isDark ? 'Light mode' : 'Dark mode';
    };
    updateThemeToggle();
    themeToggle.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = nextTheme;
        localStorage.setItem('kcart-theme', nextTheme);
        updateThemeToggle();
    });
}

const rentalForm = document.querySelector('[data-rental-form]');
if (rentalForm) {
    const car = rentalForm.querySelector('[data-car-rate]');
    const start = rentalForm.querySelector('[name="start_date"]');
    const end = rentalForm.querySelector('[name="expected_return_date"]');
    const estimate = rentalForm.querySelector('[data-rental-estimate]');
    const updateEstimate = () => {
        const rate = Number(car?.selectedOptions[0]?.dataset.rate || 0);
        const startTime = start?.value ? new Date(start.value).getTime() : 0;
        const endTime = end?.value ? new Date(end.value).getTime() : 0;
        if (!rate || !startTime || !endTime || endTime <= startTime) {
            if (estimate) estimate.textContent = 'Choose a vehicle and valid rental dates';
            return;
        }
        const days = Math.max(1, Math.ceil((endTime - startTime) / 86400000));
        if (estimate) estimate.textContent = `₱${(days * rate).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} · ${days} day${days === 1 ? '' : 's'}`;
    };
    car?.addEventListener('change', updateEstimate);
    start?.addEventListener('input', updateEstimate);
    end?.addEventListener('input', updateEstimate);
}
