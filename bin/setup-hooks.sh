#!/usr/bin/env bash
# Activa los hooks versionados en .githooks/ (se ejecuta una vez después de clonar).
set -e
cd "$(dirname "$0")/.."
git config core.hooksPath .githooks
chmod +x .githooks/* bin/*.sh bin/*.php 2>/dev/null || true
echo "✓ Hooks activados (core.hooksPath = .githooks)."
