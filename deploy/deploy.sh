#!/usr/bin/env bash
# Deploy one image to the panel stack on 192.168.1.210 and roll back if it does not serve.
#
#   deploy/deploy.sh ghcr.io/howto-software/howtoosoftware-panel-ptero@sha256:...
#
# Runs as htsadmin from a GitHub Actions self-hosted runner. Needs the docker group
# and nothing else - no sudo, no access to /home/malaio.
set -euo pipefail

IMAGE_REF="${1:?usage: deploy.sh <image-ref>}"
STACK="${PANEL_STACK_DIR:-$HOME/hts-panel}"
COMPOSE_SRC="$(cd "$(dirname "$0")" && pwd)/compose.prod.yml"
WAIT_SECONDS="${PANEL_WAIT_SECONDS:-300}"

log() { printf '%s  %s\n' "$(date -u +%H:%M:%SZ)" "$*"; }
die() { printf '::error::%s\n' "$*" >&2; exit 1; }

[ -d "$STACK" ]            || die "$STACK does not exist - run deploy/adopt-stack.sh --apply first."
[ -f "$STACK/.env.panel" ] || die "$STACK/.env.panel is missing - run deploy/adopt-stack.sh --apply first."
[ -f "$STACK/.env" ]       || die "$STACK/.env is missing - run deploy/adopt-stack.sh --apply first."

compose() { docker compose --project-directory "$STACK" -f "$STACK/compose.prod.yml" "$@"; }

# Read a key from the stack .env without sourcing it (values may contain anything).
env_get() { sed -n "s/^$1=//p" "$STACK/.env" | tail -1; }

env_set() {
    local key="$1" value="$2" tmp
    tmp="$(mktemp "$STACK/.env.XXXXXX")"
    grep -v "^${key}=" "$STACK/.env" > "$tmp" || true
    printf '%s=%s\n' "$key" "$value" >> "$tmp"
    chmod 600 "$tmp"
    mv -f "$tmp" "$STACK/.env"
}

BIND="$(env_get PANEL_BIND)";  BIND="${BIND:-192.168.1.210}"
PORT="$(env_get PANEL_PORT)";  PORT="${PORT:-8088}"
HEALTH_URL="http://${BIND}:${PORT}/"

wait_until_serving() {
    local deadline=$((SECONDS + WAIT_SECONDS)) code
    # First boot after a deploy runs `artisan migrate --seed --force`, so this is slow.
    while [ "$SECONDS" -lt "$deadline" ]; do
        if ! docker compose --project-directory "$STACK" -f "$STACK/compose.prod.yml" \
                ps --status running --services 2>/dev/null | grep -qx panel; then
            sleep 3
            continue
        fi
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$HEALTH_URL" || echo 000)"
        if [ "$code" = "200" ]; then
            log "panel answered 200 at $HEALTH_URL"
            return 0
        fi
        sleep 5
    done
    log "panel never returned 200 at $HEALTH_URL within ${WAIT_SECONDS}s (last code: ${code:-none})"
    return 1
}

PREVIOUS_IMAGE="$(env_get PANEL_IMAGE)"
log "current image:  ${PREVIOUS_IMAGE:-<none>}"
log "deploying:      $IMAGE_REF"

# Ship compose changes with the application.
install -m 0644 "$COMPOSE_SRC" "$STACK/compose.prod.yml"

log "pulling image"
docker pull --quiet "$IMAGE_REF" >/dev/null

env_set PANEL_IMAGE "$IMAGE_REF"

log "bringing the stack up"
if compose up -d --remove-orphans && wait_until_serving; then
    log "deploy ok"
    printf '%s\n' "$IMAGE_REF" > "$STACK/.last-good-image"
    docker image prune -f --filter dangling=true >/dev/null 2>&1 || true

    RUNNING_REV="$(docker inspect howtoosoftware-panel-panel-1 \
        --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' 2>/dev/null || true)"
    log "running revision: ${RUNNING_REV:-unlabelled}"
    exit 0
fi

log "DEPLOY FAILED - rolling back"
if [ -z "$PREVIOUS_IMAGE" ]; then
    compose logs --tail 80 panel || true
    die "no previous image recorded in $STACK/.env; the stack is down and needs a human."
fi

env_set PANEL_IMAGE "$PREVIOUS_IMAGE"
compose up -d --remove-orphans || true

if wait_until_serving; then
    log "rolled back to $PREVIOUS_IMAGE"
    # Migrations are NOT reverted: the entrypoint runs `migrate --seed --force` on every
    # boot, so a schema change from the failed image is still applied.
    die "deploy of $IMAGE_REF failed; rolled back to $PREVIOUS_IMAGE. Check for applied migrations."
fi

compose logs --tail 80 panel || true
die "deploy of $IMAGE_REF failed AND rollback to $PREVIOUS_IMAGE did not come up. Panel is DOWN."
