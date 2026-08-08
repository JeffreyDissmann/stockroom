#!/usr/bin/env node
// Re-apply the `node_modules` -> `node_modules.nosync` symlink after an install.
//
// Why this exists: this project lives in an iCloud-synced folder, which evicts
// large files out of dependency directories and corrupts them mid-session (see
// CLAUDE.md -> memory_icloud_nosync). The workaround is that iCloud ignores any
// path ending in `.nosync`, so `vendor/` and `node_modules/` are symlinks to
// `vendor.nosync/` and `node_modules.nosync/`.
//
// npm (>= 11) does not write through that symlink: it deletes it and creates a
// real `node_modules/` directory, silently putting ~250 MB back under iCloud's
// control. This script restores the arrangement, and is wired into the
// `postinstall` script so every `npm install` / `npm ci` self-heals.
//
// No-ops unless the workaround is already in use on this machine, so CI (which
// has a plain `node_modules/` and no `.nosync` dirs) is left alone.

import { existsSync, lstatSync, renameSync, rmSync, symlinkSync } from 'node:fs';

const LINK = 'node_modules';
const TARGET = 'node_modules.nosync';

/** The workaround is in use if either `.nosync` dependency dir is present. */
function workaroundInUse() {
    return existsSync(TARGET) || existsSync('vendor.nosync');
}

if (!workaroundInUse()) {
    process.exit(0);
}

if (!existsSync(LINK)) {
    console.log(`nosync-relink: no ${LINK}/ to relink, skipping`);
    process.exit(0);
}

if (lstatSync(LINK).isSymbolicLink()) {
    process.exit(0);
}

// npm replaced the symlink with a real directory. Whatever is in `TARGET` is
// now the stale tree that npm just superseded, so it goes; the fresh one takes
// its place. Both are gitignored and reproducible from package-lock.json.
if (existsSync(TARGET)) {
    rmSync(TARGET, { recursive: true, force: true });
}

renameSync(LINK, TARGET);
symlinkSync(TARGET, LINK);

console.log(`nosync-relink: restored ${LINK} -> ${TARGET}`);
