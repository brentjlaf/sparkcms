import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const rootDir = path.dirname(__filename);

export default {
  input: {
    global: path.join(rootDir, 'global.js'),
    script: path.join(rootDir, 'script.js')
  },
  output: {
    dir: path.join(rootDir, 'dist'),
    format: 'iife',
    entryFileNames: '[name].bundle.js',
    sourcemap: true
  },
  treeshake: false
};
