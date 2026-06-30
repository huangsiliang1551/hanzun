import assert from 'node:assert/strict';
import { shouldPollSiteBuildStatus } from '../src/utils/siteBuildPollingPolicy.js';

const cases = [
  {
    name: 'guest on login page must not poll',
    input: { authenticated: false, bootstrapping: false },
    expected: false,
  },
  {
    name: 'bootstrap phase must not poll yet',
    input: { authenticated: false, bootstrapping: true },
    expected: false,
  },
  {
    name: 'authenticated session may poll',
    input: { authenticated: true, bootstrapping: false },
    expected: true,
  },
];

for (const testCase of cases) {
  assert.equal(
    shouldPollSiteBuildStatus(testCase.input),
    testCase.expected,
    testCase.name,
  );
}

console.log('Site build polling policy validation passed.');
