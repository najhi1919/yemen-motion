# Workflow

## 1. Read-only Inspection

Inspect the current repository state, relevant files, existing diffs, recent
commits, and risk areas without changing files.

## 2. Specification

For large features or behavior changes, write or update a spec that defines
scope, constraints, acceptance criteria, and excluded work.

## 3. Task Definition

Define one concrete task with allowed files, forbidden files, required checks,
and the expected final report.

Large features need both a spec and a task. Small changes may only need a short
task when the scope is clear.

## 4. Implementation

Implement only the active task. Do not expand scope, refactor unrelated code, or
start a second task.

## 5. Build/Test

Run the checks named in the task. Report failures honestly and do not hide
warnings that may matter.

## 6. Review

Review the diff against the task scope and verify no forbidden files were
touched.

## 7. Commit

Commit only after human review and explicit approval.
