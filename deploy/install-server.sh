#!/bin/bash
#
# One-time setup of the deploy target. Run as root on the server:
#
#   sudo bash install-server.sh <path to invoiceshelf-deploy> "<CI public key>" <folder the site runs from today>
#
# Safe to re-run: it only creates what is missing, except that it always
# reinstalls the deploy script and rewrites the deploy user's authorized_keys.
# It does not touch Apache or cron; pointing the site at
# /var/www/invoice/current is a separate step (see deploy/README.md).

set -euo pipefail

SCRIPT=${1:?path to invoiceshelf-deploy}
PUBKEY=${2:?CI public key}
SOURCE=${3:?folder the site runs from today, e.g. /var/www/invoiceshelf}

APP=/var/www/invoice
# Its own user: the box's existing `deploy` user holds rrsync-only keys for
# the static sites and must not gain sudo.
DEPLOY_USER=deploy-invoiceshelf

[ "$(id -u)" -eq 0 ] || { echo "run as root" >&2; exit 1; }
[ -f "$SOURCE/.env" ] || { echo "$SOURCE/.env not found" >&2; exit 1; }
[[ $PUBKEY =~ ^ssh-ed25519\ [A-Za-z0-9+/=]+(\ .*)?$ ]] || { echo "expected an ssh-ed25519 public key" >&2; exit 1; }

id "$DEPLOY_USER" >/dev/null 2>&1 || useradd --system --create-home --shell /bin/sh "$DEPLOY_USER"

install -o root -g root -m 0755 "$SCRIPT" /usr/local/bin/invoiceshelf-deploy

# The deploy user may run exactly one thing as root, and nothing else.
echo "$DEPLOY_USER ALL=(root) NOPASSWD: /usr/local/bin/invoiceshelf-deploy" > /etc/sudoers.d/invoiceshelf-deploy
chmod 0440 /etc/sudoers.d/invoiceshelf-deploy
visudo -cf /etc/sudoers.d/invoiceshelf-deploy >/dev/null

# The key cannot open a shell, forward anything or pick its own command.
HOME_DIR=$(getent passwd "$DEPLOY_USER" | cut -d: -f6)
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 0700 "$HOME_DIR/.ssh"
echo "restrict,command=\"sudo /usr/local/bin/invoiceshelf-deploy \\\"\$SSH_ORIGINAL_COMMAND\\\"\" $PUBKEY" > "$HOME_DIR/.ssh/authorized_keys"
chown "$DEPLOY_USER":"$DEPLOY_USER" "$HOME_DIR/.ssh/authorized_keys"
chmod 0600 "$HOME_DIR/.ssh/authorized_keys"

install -d -o root -g www-data -m 0750 "$APP" "$APP/releases" "$APP/shared"

if [ ! -f "$APP/shared/.env" ]; then
    cp "$SOURCE/.env" "$APP/shared/.env"
    chown root:www-data "$APP/shared/.env"
    chmod 0640 "$APP/shared/.env"
fi

if [ ! -d "$APP/shared/storage" ]; then
    cp -a "$SOURCE/storage" "$APP/shared/storage"
    # Compiled views and cached config belong to the release that made them.
    find "$APP/shared/storage/framework/views" -type f -name '*.php' -delete
    chown -R www-data:www-data "$APP/shared/storage"
    chmod -R ug+rwX,o-rwx "$APP/shared/storage"
fi

echo "ready: $APP (deploy user: $DEPLOY_USER)"
