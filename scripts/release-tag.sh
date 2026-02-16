#!/usr/bin/env bash
set -euo pipefail

LATEST_TAG="$(git tag --list 'v*' --sort=-v:refname | head -n 1 || true)"

if [[ -z "${LATEST_TAG}" ]]; then
  SUGGESTED_TAG="v0.1.0"
else
  VERSION="${LATEST_TAG#v}"
  IFS='.' read -r MAJOR MINOR PATCH <<<"${VERSION}"
  if [[ -z "${MAJOR:-}" || -z "${MINOR:-}" || -z "${PATCH:-}" ]]; then
    echo "Latest tag '${LATEST_TAG}' is not semantic (vX.Y.Z). Provide tag explicitly." >&2
    exit 1
  fi

  if ! [[ "${MAJOR}" =~ ^[0-9]+$ && "${MINOR}" =~ ^[0-9]+$ && "${PATCH}" =~ ^[0-9]+$ ]]; then
    echo "Latest tag '${LATEST_TAG}' is not semantic (vX.Y.Z). Provide tag explicitly." >&2
    exit 1
  fi

  SUGGESTED_TAG="v${MAJOR}.${MINOR}.$((PATCH + 1))"
fi

TAG="${1:-}"
if [[ -z "${TAG}" ]]; then
  TAG="${SUGGESTED_TAG}"
  if [[ -t 0 ]]; then
    read -r -p "Suggested next patch tag is ${SUGGESTED_TAG}. Use it? [Y/n] " ANSWER
    if [[ "${ANSWER:-}" =~ ^[Nn]$ ]]; then
      read -r -p "Enter tag (vX.Y.Z): " TAG
    fi
  fi
fi

if ! [[ "${TAG}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid tag '${TAG}'. Expected format vX.Y.Z" >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree is not clean. Commit or stash changes before tagging." >&2
  exit 1
fi

if git rev-parse --verify "refs/tags/${TAG}" >/dev/null 2>&1; then
  echo "Tag '${TAG}' already exists locally." >&2
  exit 1
fi

echo "Pushing current branch HEAD to origin..."
git push origin HEAD

echo "Creating tag ${TAG}..."
git tag "${TAG}"

echo "Pushing tag ${TAG} to origin..."
git push origin "${TAG}"

echo "Release tag pushed: ${TAG}"
