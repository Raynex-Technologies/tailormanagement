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

const meaningfulLength = (value) => String(value ?? '').replace(/[,  \s]/g, '').length;

const caretForMeaningfulLength = (formatted, expectedLength) => {
    if (expectedLength <= 0) {
        return 0;
    }

    let seen = 0;
    for (let index = 0; index < formatted.length; index += 1) {
        if (! /[,  \s]/.test(formatted[index])) {
            seen += 1;
        }
        if (seen >= expectedLength) {
            return index + 1;
        }
    }

    return formatted.length;
};

const formatActiveMoneyInput = (input) => {
    const selectionStart = input.selectionStart ?? input.value.length;
    const selectionEnd = input.selectionEnd ?? selectionStart;
    const meaningfulStart = meaningfulLength(input.value.slice(0, selectionStart));
    const meaningfulEnd = meaningfulLength(input.value.slice(0, selectionEnd));
    const formatted = formatMoneyValue(input.value);

    input.value = formatted;
    input.setSelectionRange(
        caretForMeaningfulLength(formatted, meaningfulStart),
        caretForMeaningfulLength(formatted, meaningfulEnd),
    );

    input.dispatchEvent(new CustomEvent('tailor-money-input', {
        bubbles: true,
        detail: {
            raw: normalizeMoneyValue(formatted),
            formatted,
        },
    }));
};

const decimalParts = (value) => {
    const normalized = normalizeMoneyValue(value);
    const match = normalized.match(/^(\d*)(?:\.(\d*))?$/);
    if (! match) {
        return null;
    }

    const integer = match[1] || '0';
    const fraction = match[2] || '';

    return {
        digits: BigInt(`${integer}${fraction}` || '0'),
        scale: fraction.length,
    };
};

const multiplyMoneyValue = (quantity, unitPrice) => {
    const left = decimalParts(quantity);
    const right = decimalParts(unitPrice);
    if (! left || ! right) {
        return '0';
    }

    const scale = left.scale + right.scale;
    const product = (left.digits * right.digits).toString().padStart(scale + 1, '0');
    if (scale === 0) {
        return product;
    }

    const decimalIndex = product.length - scale;
    const normalized = `${product.slice(0, decimalIndex)}.${product.slice(decimalIndex)}`
        .replace(/\.0+$/, '')
        .replace(/(\.\d*?)0+$/, '$1');

    return normalized;
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

if (typeof window !== 'undefined' && ! window.TailorMoneyInputs) {
    window.TailorMoneyInputs = {
        normalize: normalizeMoneyValue,
        format: formatMoneyValue,
        multiply: multiplyMoneyValue,
        multiplyAndFormat: (quantity, unitPrice) => formatMoneyValue(multiplyMoneyValue(quantity, unitPrice)),
        formatWithin: formatMoneyInputsWithin,
    };

    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches(moneyInputSelector) && ! event.isComposing) {
            formatActiveMoneyInput(event.target);
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

export {
    caretForMeaningfulLength,
    formatMoneyValue,
    multiplyMoneyValue,
    normalizeMoneyValue,
};
