#!/usr/bin/env node
// Strip `.nosync` suffixes from `@see vendor.nosync/...` paths that Wayfinder
// writes into its generated route tree.
//
// Why this exists: this project uses an iCloud-eviction workaround where
// `vendor/` and `node_modules/` are symlinks to `vendor.nosync/` and
// `node_modules.nosync/` (see CLAUDE.md → memory_icloud_nosync). PHP's
// realpath() resolves through the symlink, so Wayfinder's `@see` comments
// embed the `.nosync` paths. CI has a real `vendor/` directory and writes
// `vendor/...` — without this scrub the committed tree drifts every time
// the maintainer regenerates locally.

import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOTS = ['resources/js/actions', 'resources/js/routes', 'resources/js/wayfinder'];
const SUFFIX_RE = /\.nosync\//g;

let touched = 0;

function walk(dir) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        const st = statSync(path);
        if (st.isDirectory()) {
            walk(path);
        } else if (st.isFile()) {
            const before = readFileSync(path, 'utf8');
            const after = before.replace(SUFFIX_RE, '/');
            if (after !== before) {
                writeFileSync(path, after);
                touched++;
            }
        }
    }
}

for (const root of ROOTS) {
    try {
        walk(root);
    } catch (e) {
        if (e.code !== 'ENOENT') {
            throw e;
        }
    }
}

if (touched > 0) {
    console.log(`scrub-nosync: cleaned ${touched} file(s)`);
}
