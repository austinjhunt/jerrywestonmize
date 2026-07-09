Run the monthly maintenance cycle for jerrywestonmize.com: back up the site, apply WordPress updates via the browser, patch the server OS, sync the resulting code changes to git, re-apply the custom modifications, and sync production back up.

This orchestrates browser control (`claude-in-chrome`), SSH to `huntaj@jwm-server`, the root-only wrapper script at `/usr/local/sbin/wp-maintenance.sh` (passwordless via a scoped sudoers rule — see `README.md` "Monthly OS and WP Update Instructions"), and the existing `reapply-customizations-post-update` skill in this repo.

**Always confirm with the user before**: any `git push` (local or on the server), running `apt-upgrade`, and running `reboot-now`. These run over a shared/production system and are hard to reverse — pause and get an explicit go-ahead even though the sudoers rule makes them technically passwordless.

## Step 0: Pre-flight

1. `ssh huntaj@jwm-server 'sudo -n /usr/local/sbin/wp-maintenance.sh git-status'` — confirms SSH + passwordless sudo still work and shows the production working tree is clean before you start.
2. In this repo: confirm you're on `main` and clean (`git status`).

## Step 1: Back up the site

1. Open a browser tab (via `claude-in-chrome`) to `https://jerrywestonmize.com/jwmsecure` (the site's custom login URL — see `functions.php` customizations).
2. Hand control to the user to log in (credentials/2FA are theirs to enter — never ask them to paste a password into chat). Wait for them to confirm they're logged in before taking control back.
3. Navigate to `https://jerrywestonmize.com/wp-admin/options-general.php?page=updraftplus`.
4. Click "Backup Now", wait for the backup to complete, and confirm success in the UI before proceeding.

## Step 2: Apply WordPress dashboard updates

1. Navigate to the WP admin Updates screen and apply all available plugin, theme, and core updates.
2. Record what was updated and to which versions (plugin/theme/core names + version numbers) — you'll need this for the commit message in Step 4.
3. Note whether WP **core** was updated specifically — this determines whether Step 7's `remove-login-file` is needed (core updates restore the default `wp-login.php`, which is a security hole on this site since login is routed through `jwmsecure.php`).

## Step 3: Patch the server OS

1. Confirm with the user before proceeding (this step patches and may reboot the production server).
2. `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh apt-upgrade'`
3. Check the output for `REBOOT_REQUIRED=yes` or `=no`.
4. If reboot is required: confirm with the user explicitly, then `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh reboot-now'`. The SSH connection will drop. Wait about 60 seconds, then reconnect and verify with `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh reboot-check'` (should now report `REBOOT_REQUIRED=no`).

## Step 4: Sync WP dashboard changes to git (on production)

1. `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh git-status'` and show the user the changed files.
2. Draft a commit message from what you recorded in Step 2, e.g. `Colibri theme v1.2.3 update; WP Core 6.4.5 update; Amelia booking plugin v1.2.3 update`.
3. Confirm the message and the push with the user, then run:
   `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh git-sync "<message>"'`

## Step 5: Re-apply customizations and open a PR

1. Locally, invoke the `reapply-customizations-post-update` skill in this repo. It creates a branch, re-applies the Colibri/Amelia/login customizations if the updates wiped them out, commits, pushes, and reports a PR URL.
2. **Stop here and tell the user the PR URL.** Do not proceed to Step 6 until they confirm the PR is merged into `main`.

## Step 6: Sync production from merged main

Once the user confirms the PR is merged:

1. `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh git-pull'`

## Step 7: Post-update server hygiene

1. If WP core was updated in Step 2: `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh remove-login-file'`
2. `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh fix-permissions'`
3. `ssh huntaj@jwm-server 'sudo /usr/local/sbin/wp-maintenance.sh restart-apache'` — confirm it reports `active`.
4. Verify the live site loads (`curl -sI https://jerrywestonmize.com` or open it in the browser) and that login still works.

## Step 8: Report results

Summarize for the user:
- Backup: confirmed complete
- What was updated (plugins/themes/core + versions)
- OS packages patched; whether a reboot happened
- Whether customizations needed re-applying, and the PR URL
- Final site health check result
