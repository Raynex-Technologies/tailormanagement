import assert from 'node:assert/strict';
import {
    caretForMeaningfulLength,
    formatMoneyValue,
    multiplyMoneyValue,
    normalizeMoneyValue,
} from '../../resources/js/modules/money-inputs.js';

const typingProgression = ['7', '78', '780', '7,800', '78,000', '780,000'];
let typed = '';
for (const expected of typingProgression) {
    typed = normalizeMoneyValue(typed) + expected.replace(/\D/g, '').slice(-1);
    assert.equal(formatMoneyValue(typed), expected);
}

assert.equal(formatMoneyValue('1,250,000.50'), '1,250,000.50');
assert.equal(normalizeMoneyValue(' 1,250,000.50 '), '1250000.50');
assert.equal(formatMoneyValue('0'), '0');
assert.equal(formatMoneyValue('0.50'), '0.50');
assert.equal(formatMoneyValue(normalizeMoneyValue('780,000').slice(0, -1)), '78,000');
assert.equal(formatMoneyValue('1250000'), '1,250,000');
assert.equal(multiplyMoneyValue('3', '780000'), '2340000');
assert.equal(multiplyMoneyValue('1.5', '1250000.50'), '1875000.75');
assert.equal(caretForMeaningfulLength('780,000', 3), 3);
assert.equal(caretForMeaningfulLength('780,000', 4), 5);

console.log('money-inputs: typing, paste, backspace, decimals, caret, and exact totals passed');
