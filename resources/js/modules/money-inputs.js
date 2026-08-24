const moneyInputSelector = '[data-money-input]';

const normalizeMoneyValue = (value) => String(value ?? '')
    .trim()
    .replace(/[,\u00A0\u202F\s]/g, '');

const formatMoneyValue = (value) => {
    const normalized = normalizeMoneyValue(value);

    if (normalized === '') {
        return '';
    }

    const match = normalized.match(/^([+-]?)(\d*)(?:\.(\d*))?$/);

    if (! match) {
        return normalized;
    }

    const [, sign, integer = '', fraction] = match;
    const groupedInteger = (integer || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return fraction === undefined
        ? `${sign}${groupedInteger}`
        : `${sign}${groupedInteger}.${fraction}`;
};

const formatMoneyInput = (input) => {
    if (! (input instanceof HTMLInputElement) || document.activeElement === input) {
        return;
    }

    input.value = formatMoneyValue(input.value);
};

const formatMoneyInputsWithin = (root = document) => {
    if (root instanceof HTMLInputElement && root.matches(moneyInputSelector)) {
        formatMoneyInput(root);
    }

    root.querySelectorAll?.(moneyInputSelector).forEach(formatMoneyInput);
};

if (! window.TailorMoneyInputs) {
    window.TailorMoneyInputs = {
        normalize: normalizeMoneyValue,
        format: formatMoneyValue,
        formatWithin: formatMoneyInputsWithin,
    };

    document.addEventListener('focusin', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches(moneyInputSelector)) {
            event.target.value = normalizeMoneyValue(event.target.value);
        }
    });

    document.addEventListener('blur', (event) => {
        if (! (event.target instanceof HTMLInputElement) || ! event.target.matches(moneyInputSelector)) {
            return;
        }

        const input = event.target;
        input.value = normalizeMoneyValue(input.value);

        window.setTimeout(() => formatMoneyInput(input), 0);
    }, true);

    document.addEventListener('DOMContentLoaded', () => formatMoneyInputsWithin());
    document.addEventListener('livewire:navigated', () => formatMoneyInputsWithin());
    document.addEventListener('livewire:initialized', () => {
        formatMoneyInputsWithin();

        window.Livewire?.hook('morph.updated', ({ el }) => formatMoneyInputsWithin(el));
    });

    const observer = new MutationObserver((records) => {
        records.forEach(({ addedNodes }) => {
            addedNodes.forEach((node) => {
                if (node instanceof Element) {
                    formatMoneyInputsWithin(node);
                }
            });
        });
    });

    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', () => {
            observer.observe(document.body, { childList: true, subtree: true });
        }, { once: true });
    }
}
