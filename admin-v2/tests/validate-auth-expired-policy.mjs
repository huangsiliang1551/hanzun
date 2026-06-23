import assert from 'node:assert/strict';
import { shouldDispatchAuthExpiredEvent } from '../src/utils/authExpiredPolicy.js';

const cases = [
  {
    name: 'protected request should dispatch expired event',
    input: { skipAuth: false, suppressAuthExpiredEvent: false },
    expected: true,
  },
  {
    name: 'skipAuth request should stay silent',
    input: { skipAuth: true, suppressAuthExpiredEvent: false },
    expected: false,
  },
  {
    name: 'silent bootstrap request should stay silent',
    input: { skipAuth: false, suppressAuthExpiredEvent: true },
    expected: false,
  },
];

for (const testCase of cases) {
  assert.equal(
    shouldDispatchAuthExpiredEvent(testCase.input),
    testCase.expected,
    testCase.name,
  );
}

console.log('Auth expired policy validation passed.');
