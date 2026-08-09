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
//
// This is a local convenience and must NEVER fail an install: a non-zero exit
// here fails `npm ci`, and that takes the Docker build with it. Everything below
// is wrapped so the worst case is a warning. The Dockerfile copies only the
// manifests before `npm ci`, so this file often isn't there at all — the
// `postinstall` entry tests for it before invoking node.

import { existsSync, lstatSync, renameSync, rmSync, symlinkSync } from 'node:fs';

const LINK = 'node_modules';
const TARGET = 'node_modules.nosync';

/** The workaround is in use if either `.nosync` dependency dir is present. */
function workaroundInUse() {
    return existsSync(TARGET) || existsSync('vendor.nosync');
}

try {
    if (!workaroundInUse() || !existsSync(LINK) || lstatSync(LINK).isSymbolicLink()) {
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
} catch (e) {
    console.warn(`nosync-relink: skipped (${e.message})`);
}
