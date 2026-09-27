#!/usr/bin/env bash
# Install the GitHub Actions self-hosted runner that deploy.yaml targets.
#
#   deploy/install-runner.sh --token <REGISTRATION_TOKEN>
#
# Get the token from:
#   https://github.com/HowTo-Software/howtoosoftware-panel-ptero/settings/actions/runners/new
# It expires in one hour and is single-use.
#
# Run as htsadmin ON 192.168.1.210. Everything is unprivileged except `svc.sh install`,
# which registers the systemd unit and will prompt you for your sudo password.
set -euo pipefail

REPO_URL="https://github.com/HowTo-Software/howtoosoftware-panel-ptero"
RUNNER_DIR="${RUNNER_DIR:-$HOME/actions-runner}"
RUNNER_VERSION="${RUNNER_VERSION:-2.337.0}"
LABELS="hts-panel"
TOKEN=""

while [ $# -gt 0 ]; do
    case "$1" in
        --token) TOKEN="${2:?--token needs a value}"; shift 2 ;;
        --version) RUNNER_VERSION="${2:?}"; shift 2 ;;
        *) echo "unknown argument: $1" >&2; exit 1 ;;
    esac
done

die() { printf 'error: %s\n' "$*" >&2; exit 1; }

[ -n "$TOKEN" ] || die "missing --token (get one from $REPO_URL/settings/actions/runners/new)"
id -nG | tr ' ' '\n' | grep -qx docker || die "$(id -un) is not in the docker group; the deploy job cannot work."
[ ! -d "$RUNNER_DIR/.runner" ] || die "$RUNNER_DIR is already configured. Remove it first, or reuse it."

mkdir -p "$RUNNER_DIR"
cd "$RUNNER_DIR"

TARBALL="actions-runner-linux-x64-${RUNNER_VERSION}.tar.gz"
if [ ! -f "$TARBALL" ]; then
    echo "downloading runner ${RUNNER_VERSION}"
    curl -fsSL -o "$TARBALL" \
        "https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/${TARBALL}"
fi
tar xzf "$TARBALL"

echo "configuring"
# --unattended so the token never has to be typed at a prompt.
./config.sh \
    --unattended \
    --replace \
    --url "$REPO_URL" \
    --token "$TOKEN" \
    --name "2htswebserver" \
    --labels "$LABELS" \
    --work "_work"

cat <<'NEXT'

Configured. Now install it as a service (this step asks for your sudo password):

    cd ~/actions-runner
    sudo ./svc.sh install "$(id -un)"
    sudo ./svc.sh start
    sudo ./svc.sh status

The runner must run as the same user that is in the docker group - pass $(id -un),
not root, or the deploy job will write root-owned files into your home directory.
NEXT
