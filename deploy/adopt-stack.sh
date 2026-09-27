#!/usr/bin/env bash
# One-time: move the panel stack out from behind /home/malaio so CI can deploy it.
#
#   deploy/adopt-stack.sh            # report only
#   deploy/adopt-stack.sh --apply
#
# Run as htsadmin ON 192.168.1.210. Needs the docker group and nothing else.
# It reads the live container's configuration instead of /home/malaio/.../compose.yml,
# which htsadmin cannot open. Secret VALUES are written to a 0600 file and are never
# printed.
#
# This only writes files. It does not touch the running containers - the first
# approved deploy performs the switchover, with rollback available.
set -euo pipefail

APPLY=0
[ "${1:-}" = "--apply" ] && APPLY=1

STACK="${PANEL_STACK_DIR:-$HOME/hts-panel}"
COMPOSE_SRC="$(cd "$(dirname "$0")" && pwd)/compose.prod.yml"
CONTAINER=howtoosoftware-panel-panel-1

say() { printf '\n== %s ==\n' "$1"; }
die() { printf 'error: %s\n' "$*" >&2; exit 1; }

docker info >/dev/null 2>&1 || die "cannot talk to docker as $(id -un); this must run on the panel host."
docker inspect "$CONTAINER" >/dev/null 2>&1 || die "$CONTAINER is not present - wrong host?"

say "1. current stack"
docker inspect "$CONTAINER" --format 'compose file : {{index .Config.Labels "com.docker.compose.project.config_files"}}'
docker inspect "$CONTAINER" --format 'project      : {{index .Config.Labels "com.docker.compose.project"}}'
docker inspect "$CONTAINER" --format 'image        : {{.Config.Image}}'
RUNNING_IMAGE_ID="$(docker inspect "$CONTAINER" --format '{{.Image}}')"
BIND="$(docker inspect "$CONTAINER" --format '{{range $p, $c := .HostConfig.PortBindings}}{{range $c}}{{.HostIp}}{{end}}{{end}}')"
PORT="$(docker inspect "$CONTAINER" --format '{{range $p, $c := .HostConfig.PortBindings}}{{range $c}}{{.HostPort}}{{end}}{{end}}')"
echo "published    : ${BIND:-0.0.0.0}:${PORT} -> 80"
[ -n "$PORT" ] || die "could not read the published port from $CONTAINER."

say "2. environment that comes from compose (not from the image)"
IMAGE_REF="$(docker inspect "$CONTAINER" --format '{{.Config.Image}}')"
IMG_ENV="$(mktemp)"; CTR_ENV="$(mktemp)"; OVERRIDES="$(mktemp)"
trap 'rm -f "$IMG_ENV" "$CTR_ENV" "$OVERRIDES"' EXIT
chmod 600 "$IMG_ENV" "$CTR_ENV" "$OVERRIDES"

docker inspect "$IMAGE_REF" --format '{{range .Config.Env}}{{println .}}{{end}}' > "$IMG_ENV"
docker inspect "$CONTAINER" --format '{{range .Config.Env}}{{println .}}{{end}}' > "$CTR_ENV"

# Anything not byte-identical to an image entry was supplied by compose. Comparing whole
# KEY=VALUE lines (not just keys) also catches compose overriding an image default.
while IFS= read -r line; do
    [ -n "$line" ] || continue
    grep -qxF -- "$line" "$IMG_ENV" || printf '%s\n' "$line" >> "$OVERRIDES"
done < "$CTR_ENV"

BAD="$(grep -cve '^[A-Za-z_][A-Za-z0-9_]*=' "$OVERRIDES" || true)"
[ "$BAD" = "0" ] || die "$BAD extracted line(s) are not KEY=VALUE (a value probably contains a newline). Extract by hand."

QUOTED="$(grep -cE '^[A-Za-z_][A-Za-z0-9_]*="' "$OVERRIDES" || true)"
[ "$QUOTED" = "0" ] || echo "warning: $QUOTED value(s) start with a double quote; compose strips quotes in env_file. Verify those after the first deploy."

echo "$(wc -l < "$OVERRIDES") variables will be written to $STACK/.env.panel (0600). Keys only:"
sed 's/=.*//' "$OVERRIDES" | sort | paste -sd' ' -

say "3. volumes that must survive (APP_KEY lives in howtoo_var:/app/var/.env)"
EXPECTED_VAR_VOLUME="howtoosoftware-panel_howtoo_var"
MOUNTED_VAR_VOLUME="$(docker inspect "$CONTAINER" --format '{{range .Mounts}}{{if eq .Destination "/app/var"}}{{.Name}}{{end}}{{end}}')"
[ "$MOUNTED_VAR_VOLUME" = "$EXPECTED_VAR_VOLUME" ] || die "$EXPECTED_VAR_VOLUME is not mounted at /app/var; refusing to adopt without the existing APP_KEY volume."
docker volume ls --filter 'label=com.docker.compose.project=howtoosoftware-panel' --format '  {{.Name}}'

say "4. plan"
cat <<PLAN
  $STACK/compose.prod.yml   <- deploy/compose.prod.yml from this checkout
  $STACK/.env.panel         <- $(wc -l < "$OVERRIDES") vars from the live container, mode 0600
  $STACK/.env               <- PANEL_IMAGE / PANEL_BIND / PANEL_PORT, mode 0600
  local tag                 <- the running image, so the first CI deploy can roll back to it
PLAN

if [ "$APPLY" -eq 0 ]; then
    say "dry run"
    echo "nothing written. re-run with --apply"
    exit 0
fi

say "5. writing"
umask 077
mkdir -p "$STACK"
install -m 0644 "$COMPOSE_SRC" "$STACK/compose.prod.yml"
install -m 0600 "$OVERRIDES" "$STACK/.env.panel"

ROLLBACK_TAG="hts-panel:adopted-$(date +%Y%m%d-%H%M%S)"
docker tag "$RUNNING_IMAGE_ID" "$ROLLBACK_TAG"
echo "tagged the running image as $ROLLBACK_TAG"

{
    printf 'PANEL_IMAGE=%s\n' "$ROLLBACK_TAG"
    printf 'PANEL_BIND=%s\n'  "${BIND:-192.168.1.210}"
    printf 'PANEL_PORT=%s\n'  "$PORT"
} > "$STACK/.env"
chmod 600 "$STACK/.env"

say "6. validating"
docker compose --project-directory "$STACK" -f "$STACK/compose.prod.yml" config --quiet \
    && echo "compose file parses and every interpolated variable resolves."

say "done"
cat <<'NEXT'
Nothing was restarted. The running containers are untouched.

Next:
  1. Register the self-hosted runner:  deploy/install-runner.sh
  2. Create the "production" environment in GitHub with required reviewers.
  3. Merge to master, approve the deploy, and watch it switch the stack over.

After the first successful deploy, retire the old stack so the two cannot fight:
    sudo -u malaio sh -c 'cd /home/malaio/howtoosoftware-panel && mv compose.yml compose.yml.retired'
Do NOT run `docker compose -f /home/malaio/howtoosoftware-panel/compose.yml up` again -
it shares the project name and would rebuild over the deployed image.
NEXT
