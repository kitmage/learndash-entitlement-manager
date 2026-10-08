'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/account.js'), 'utf8');

function setup({ secure = true, clipboard, fallback = true, loading = false } = {}) {
  const events = {};
  const inputs = ['https://training.example/enroll/first', 'https://training.example/enroll/second'].map(value => ({
    value, focused: false, selected: false,
    focus() { this.focused = true; },
    select() { this.selected = true; }
  }));
  const statuses = inputs.map(() => ({ textContent: '' }));
  const buttons = inputs.map((input, index) => ({
    hidden: true, focused: false,
    dataset: { copied: 'Enrollment link copied.', failed: 'Select the enrollment link and copy it manually.' },
    focus() { this.focused = true; },
    addEventListener(event, callback) { this[event] = callback; },
    closest() { return { querySelector: selector => selector === 'input' ? input : statuses[index] }; }
  }));
  let fallbackCalls = 0;
  const document = {
    readyState: loading ? 'loading' : 'complete',
    querySelectorAll: () => buttons,
    addEventListener(event, callback) { events[event] = callback; },
    execCommand(command) {
      assert.equal(command, 'copy');
      fallbackCalls++;
      if (fallback instanceof Error) throw fallback;
      return fallback;
    }
  };
  vm.runInNewContext(source, { document, navigator: { clipboard }, window: { isSecureContext: secure } });
  return { buttons, inputs, statuses, events, fallbackCalls: () => fallbackCalls };
}

const flush = () => new Promise(resolve => setImmediate(resolve));

test('copies the clicked grant, announces success, and retains button focus', async () => {
  const copied = [];
  const ui = setup({ clipboard: { writeText: value => { copied.push(value); return Promise.resolve(); } } });
  assert.equal(ui.buttons[0].hidden, false);
  ui.buttons[1].click();
  await flush();
  assert.deepEqual(copied, [ui.inputs[1].value]);
  assert.equal(ui.statuses[1].textContent, 'Enrollment link copied.');
  assert.equal(ui.statuses[0].textContent, '');
  assert.equal(ui.buttons[1].focused, true);
  assert.equal(ui.fallbackCalls(), 0);
});

test('clipboard denial falls back to copying the selected read-only link', async () => {
  const ui = setup({ clipboard: { writeText: () => Promise.reject(new Error('denied')) } });
  ui.buttons[0].click();
  await flush();
  assert.equal(ui.fallbackCalls(), 1);
  assert.equal(ui.inputs[0].selected, true);
  assert.equal(ui.statuses[0].textContent, 'Enrollment link copied.');
});

test('insecure pages use the fallback without invoking the clipboard API', () => {
  const ui = setup({ secure: false, clipboard: { writeText: () => { throw new Error('must not call'); } } });
  ui.buttons[0].click();
  assert.equal(ui.fallbackCalls(), 1);
  assert.equal(ui.statuses[0].textContent, 'Enrollment link copied.');
});

test('failed or unavailable copying leaves the link selected and gives manual guidance', () => {
  for (const fallback of [false, new Error('unsupported')]) {
    const ui = setup({ fallback });
    ui.buttons[0].click();
    assert.equal(ui.inputs[0].selected, true);
    assert.equal(ui.inputs[0].focused, true);
    assert.equal(ui.buttons[0].focused, false);
    assert.equal(ui.statuses[0].textContent, 'Select the enrollment link and copy it manually.');
  }
});

test('initializes after DOM readiness when loaded before the account content', () => {
  const ui = setup({ loading: true });
  assert.equal(ui.buttons[0].hidden, true);
  ui.events.DOMContentLoaded();
  assert.equal(ui.buttons[0].hidden, false);
  ui.buttons[0].click();
  assert.equal(ui.statuses[0].textContent, 'Enrollment link copied.');
});
