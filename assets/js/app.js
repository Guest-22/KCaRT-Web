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
        const rate = Number(car?.dataset.rate || 0);
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

document.querySelectorAll('[data-search-picker]').forEach((picker) => {
    const input = picker.querySelector('[data-picker-input]');
    const value = picker.querySelector('[data-picker-value]');
    const list = picker.querySelector('[role="listbox"]');
    const options = [...picker.querySelectorAll('.search-picker-option')];
    const empty = picker.querySelector('.search-picker-empty');
    if (!(input instanceof HTMLInputElement) || !(value instanceof HTMLInputElement) || !(list instanceof HTMLElement)) return;
    options.forEach((option, index) => {
        if (!option.id) option.id = `${list.id || 'search-picker'}-option-${index}`;
    });

    let activeIndex = -1;
    const matches = () => options.filter((option) => !option.hidden);
    const close = () => {
        list.hidden = true;
        picker.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        options.forEach((option) => option.classList.remove('is-active'));
        activeIndex = -1;
    };
    const open = () => {
        list.hidden = false;
        picker.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
    };
    const filter = (invalidateSelection = true) => {
        const query = input.value.trim().toLocaleLowerCase();
        const filtered = options.filter((option) => (option.dataset.search || option.dataset.label || '').toLocaleLowerCase().includes(query));
        options.forEach((option) => {
            option.hidden = !filtered.includes(option);
            option.classList.remove('is-active');
            option.setAttribute('aria-selected', option.dataset.value === value.value && value.value ? 'true' : 'false');
        });
        if (empty) empty.hidden = filtered.length > 0;
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
        if (invalidateSelection) {
            value.value = '';
            delete input.dataset.rate;
            input.setCustomValidity(input.value ? 'Choose an item from the suggestions.' : '');
        }
        open();
        if (invalidateSelection && input.matches('[data-car-rate]')) input.dispatchEvent(new Event('change'));
    };
    const select = (option) => {
        input.value = option.dataset.label || option.textContent.trim();
        value.value = option.dataset.value || '';
        input.dataset.rate = option.dataset.rate || '';
        input.setCustomValidity('');
        options.forEach((item) => {
            item.setAttribute('aria-selected', item === option ? 'true' : 'false');
            item.classList.remove('is-active');
        });
        close();
        if (input.matches('[data-car-rate]')) input.dispatchEvent(new Event('change'));
    };
    const setActive = (index) => {
        const filtered = matches();
        if (!filtered.length) return;
        activeIndex = (index + filtered.length) % filtered.length;
        options.forEach((option) => option.classList.remove('is-active'));
        const option = filtered[activeIndex];
        option.classList.add('is-active');
        input.setAttribute('aria-activedescendant', option.id);
        option.scrollIntoView({ block: 'nearest' });
    };

    input.addEventListener('focus', () => {
        filter(false);
    });
    input.addEventListener('input', () => filter());
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (list.hidden) filter(false);
            setActive(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) filter(false);
            setActive(activeIndex < 0 ? matches().length - 1 : activeIndex - 1);
        } else if (event.key === 'Enter' && !list.hidden && activeIndex >= 0) {
            event.preventDefault();
            select(matches()[activeIndex]);
        } else if (event.key === 'Escape') {
            close();
        }
    });
    list.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const option = target.closest('.search-picker-option');
        if (option instanceof HTMLButtonElement) select(option);
    });
    list.addEventListener('mousedown', (event) => event.preventDefault());
    input.form?.addEventListener('submit', (event) => {
        if (!value.value) {
            input.setCustomValidity('Choose an item from the suggestions.');
            input.reportValidity();
            event.preventDefault();
        }
    });
    document.addEventListener('click', (event) => {
        if (event.target instanceof Node && !picker.contains(event.target)) close();
    });
});

document.querySelectorAll('form[data-password-form]').forEach((form) => {
    const password = form.querySelector('[data-password-source]');
    const confirmation = form.querySelector('[data-password-confirm]');
    if (!(password instanceof HTMLInputElement) || !(confirmation instanceof HTMLInputElement)) return;
    const validateConfirmation = () => {
        confirmation.setCustomValidity(password.value === confirmation.value ? '' : 'Passwords do not match.');
    };
    password.addEventListener('input', validateConfirmation);
    confirmation.addEventListener('input', validateConfirmation);
    form.addEventListener('submit', validateConfirmation);
});
