# Deploying invoice.c0deweb.com

This is C0de Web Ltd's fork of InvoiceShelf. Our changes live on the `c0deweb`
branch, which started from upstream tag `2.4.7`; upstream releases are merged into it.

Production is the `sl` DigitalOcean droplet, which also serves c0deweb.com,
sl.c0deweb.com and stephaniestephan.uk on Ubuntu's PHP 8.3. InvoiceShelf alone runs on
PHP 8.4-FPM (ondrej PPA, pinned in `/etc/apt/preferences.d/ondrej-php-pin` to `php8.4*`
and `php-common`), so nothing here may change PHP, Apache or MySQL globally.

Addresses are deliberately not written down here; they live in the repository
secrets and in `~/.ssh/config`.

## Deploying

1. Push to `c0deweb`.
2. In GitHub: **Actions → Deploy → Run workflow**, branch `c0deweb`, action `deploy`.
   It runs the test suite (`tests.yaml`), builds the release once (`composer install
   --no-dev`, then `pnpm install && pnpm build` for the Vue app in `public/build`), and
   ships exactly that build. The server never runs Composer or pnpm.

On the server, `invoiceshelf-deploy` then:

- unpacks the release into `/var/www/invoice/releases/<utc time>-<sha>/`
- links the shared `.env` and `storage/` (uploads, logs) into it
- dumps the database to `/var/backups/invoiceshelf/` (last 10 kept)
- runs `migrate --force`, `config:cache`, `route:cache`, `view:cache` as `www-data` on PHP 8.4
- points `/var/www/invoice/current` at the new release and reloads PHP 8.4-FPM
- checks `https://<APP_URL>/login`; if it fails, it switches straight back to the
  previous release and the workflow fails
- keeps the last 5 releases

Don't use InvoiceShelf's built-in updater (Settings → Update): it would overwrite the
release folder with upstream code. Updates come from merging upstream into `c0deweb`.

## Rolling back

**Actions → Deploy → Run workflow**, action `rollback`. The symlink goes back to the
previous release in a second.

Rollback does **not** undo migrations. Keep them backward compatible, so the previous
release still runs on the new schema: add columns and tables, and drop or rename only
in a later release. If a migration has to be undone, restore the dump the deploy
took in `/var/backups/invoiceshelf/`.

## Status

```sh
ssh sl sudo -u deploy-invoiceshelf sudo /usr/local/bin/invoiceshelf-deploy status
```

## How the CI key is contained

CI has no one to approve a prompt, so it uses a passphrase-less key. On the server that key
belongs to the `deploy-invoiceshelf` user, and `authorized_keys` pins it:

```
restrict,command="sudo /usr/local/bin/invoiceshelf-deploy \"$SSH_ORIGINAL_COMMAND\"" ssh-ed25519 ...
```

- `restrict`: no shell, pty, port or agent forwarding.
- `command=`: whatever the client asks for, only the deploy script runs, with the request as a
  single argument that it validates (`deploy <sha>`, `rollback`, `status`).
- `/etc/sudoers.d/invoiceshelf-deploy` lets `deploy-invoiceshelf` run that one script as root,
  nothing else.

The box's existing `deploy` user is separate on purpose: it holds rrsync-only keys for the
static sites and has no sudo.

A leaked key can deploy a tarball of its choosing to InvoiceShelf. It cannot read the database,
the `.env` or anything else on the box.

## Repository secrets

| Secret | Value |
|---|---|
| `DEPLOY_SSH_KEY` | private half of the CI key (ed25519, no passphrase) |
| `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan -t ed25519 <server>` output |
| `DEPLOY_HOST` | `deploy-invoiceshelf@<server>` |

## One-time server setup

Kept here to rebuild the target or rotate the key.

```sh
# on your machine: a fresh CI key
ssh-keygen -t ed25519 -N '' -C 'invoiceshelf-ci' -f invoiceshelf-ci
scp deploy/invoiceshelf-deploy deploy/install-server.sh sl:/root/
ssh sl bash /root/install-server.sh /root/invoiceshelf-deploy "$(cat invoiceshelf-ci.pub)" /var/www/invoiceshelf
gh secret set DEPLOY_SSH_KEY < invoiceshelf-ci && rm invoiceshelf-ci invoiceshelf-ci.pub
```

Then, once the first deploy has created `/var/www/invoice/current`:

- point both vhosts (`invoice.c0deweb.com.conf`, `invoice.c0deweb.com-le-ssl.conf`) at
  `/var/www/invoice/current/public`; they keep the `<FilesMatch "\.php$">` block that hands
  PHP to `proxy:unix:/run/php/php8.4-fpm.sock`
- point `/etc/cron.d/invoiceshelf` at `/var/www/invoice/current`
- copy any uploads made since setup into `/var/www/invoice/shared/storage`, and reload Apache
