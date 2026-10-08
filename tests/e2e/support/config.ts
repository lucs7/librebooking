import { copyFileSync, existsSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import path from 'node:path';

// The app reads config.php on every request. The settings are global for the stack.
const dir = path.join(__dirname, '..', '.runtime', process.env.E2E_RUNTIME ?? 'host', 'config');
const configFile = path.join(dir, 'config.php');
const backupFile = path.join(dir, 'config.php.orig');

const php = (value: string | number | boolean) =>
  typeof value === 'string' ? `'${value.replace(/['\\]/g, '\\$&')}'` : String(value);

// Sets entries like 'password.disable.reset' (section 'password', entry 'disable.reset') in config.php.
export function setConfig(settings: Record<string, string | number | boolean>) {
  if (!existsSync(backupFile)) copyFileSync(configFile, backupFile);
  let content = readFileSync(backupFile, 'utf8');
  for (const [key, value] of Object.entries(settings)) {
    const [section, ...rest] = key.split('.');
    const entry = rest.join('.');
    const pattern = new RegExp(
      `(^\\s*'${section}' => \\[[\\s\\S]*?^\\s*'${entry.replace(/\./g, '\\.')}' => )[^\\n]*?,$`,
      'm'
    );
    if (!pattern.test(content)) throw new Error(`${key} not found in config.php`);
    content = content.replace(pattern, `$1${php(value)},`);
  }
  // Replace by rename: the file belongs to the container user.
  writeFileSync(`${configFile}.tmp`, content);
  renameSync(`${configFile}.tmp`, configFile);
}

export function resetConfig() {
  if (existsSync(backupFile)) {
    copyFileSync(backupFile, `${configFile}.tmp`);
    renameSync(`${configFile}.tmp`, configFile);
  }
}
