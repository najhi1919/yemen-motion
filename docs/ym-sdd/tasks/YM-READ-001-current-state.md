# YM-READ-001 Current State

Type: Read-only inspection

## Goal

Inspect the current project state before any implementation work.

## Allowed Actions

- Read files.
- Inspect Git status, logs, and diffs.
- Report findings.

## Forbidden Actions

- Do not edit files.
- Do not run installs.
- Do not add, commit, push, reset, restore, or clean.
- Do not print secrets.

## Required Output

Write an Arabic report summarizing repository status, recent commits, current
diffs, sensitive file findings, and next recommended task.
