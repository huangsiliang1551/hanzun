import { fileURLToPath } from 'node:url';
import fs from 'node:fs/promises';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const outputDir = path.resolve(__dirname, '../admin-app');

try {
  await fs.rm(outputDir, { recursive: true, force: true });
  console.log(`Removed ${outputDir}`);
} catch (error) {
  console.error(`Failed to remove ${outputDir}`);
  console.error(error);
  process.exit(1);
}
