// Usage: node prepare-config.mjs <runtime>
// Creates an empty, world-writable folder that is mounted as /config. The
// container (www-data) writes config.php into it and specs rewrite it.
import { chmodSync, mkdirSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const dir = fileURLToPath(new URL(`../.runtime/${process.argv[2]}/config`, import.meta.url));
rmSync(dir, { recursive: true, force: true });
mkdirSync(dir, { recursive: true });
chmodSync(dir, 0o777);
