import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../src/modules/settings/AIPanel.jsx', import.meta.url), 'utf8');

assert.ok(
  !source.includes('popupMatchSelectWidth={false}'),
  'AI model select popup should match the select width',
);

assert.ok(
  !source.includes('minWidth: 860'),
  'AI model dropdown should not force an oversized popup width',
);

console.log('AI settings panel validation passed.');
