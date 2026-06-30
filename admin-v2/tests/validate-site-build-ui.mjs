import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../src/providers/SiteBuildProvider.jsx', import.meta.url), 'utf8');

assert.match(
  source,
  /queued[\s\S]*已排队|\\u5df2\\u6392\\u961f/,
  'site build UI should expose Chinese label for queued status',
);

assert.match(
  source,
  /running[\s\S]*进行中|\\u8fdb\\u884c\\u4e2d/,
  'site build UI should expose Chinese label for running status',
);

assert.match(
  source,
  /floatingDismissedJobId|dismissedJobId|hiddenFloatingJobId/,
  'site build UI should support dismissing the floating task card without stopping the task',
);

assert.match(
  source,
  /关闭提醒|\\u5173\\u95ed\\u63d0\\u9192/,
  'site build floating task card should provide a close action',
);

console.log('Site build UI validation passed.');
