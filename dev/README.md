# Development stand

Drupal + ONLYOFFICE Docs in Docker, with this repository mounted into Drupal as `modules/contrib/onlyoffice`.
Needs Docker and bash (on Windows: Git Bash with Docker Desktop).

```bash
dev/stand.sh            # start, install or update Drupal, enable and configure the modules
```

Drupal is at http://localhost:8000 (`admin` / `admin`), Docs at http://localhost:8080.
The stand enables Media, `onlyoffice` and `onlyoffice_form`, fills in the connection settings and runs the same
connection check as the settings form. Module edits are live; the Drupal codebase lives in a Docker volume, not on disk.

| Command | |
|---|---|
| `dev/stand.sh up` | start (default); also runs `updatedb` after a new update hook |
| `dev/stand.sh down` | stop, keep data |
| `dev/stand.sh destroy` | stop and delete Drupal, database and files |
| `dev/stand.sh drush cr` | run a drush command |
| `dev/stand.sh composer <args>` | run composer in the Drupal codebase |
| `dev/stand.sh shell` / `logs [service]` | shell in the Drupal container / follow logs |

Settings go in `dev/.env` (git-ignored); defaults shown:

```
DRUPAL_VERSION=11    # tag of the drupal image
PHP_VERSION=8.4
DRUPAL_PORT=8000
DS_PORT=8080
DS_VERSION=latest
DS_JWT_SECRET=onlyoffice-drupal-dev-stand-secret   # at least 32 characters
ADMIN_USER=admin
ADMIN_PASS=admin
```

Each Drupal version gets a stand of its own (Docker project `onlyoffice-drupal-dev-<version>`), so switching the
version keeps the other stands. To check the oldest supported Drupal next to the default one, give it other ports and
pass the same variables to its other commands (values in `dev/.env` take precedence):

```bash
DRUPAL_VERSION=10.3 PHP_VERSION=8.3 DRUPAL_PORT=8001 DS_PORT=8081 dev/stand.sh
```

Mail is not sent: the stand keeps it with Drupal's test mail collector. The private file system is set up in
`/opt/drupal/private`; public files stay the default, so select private files in a file field to use it.

## How the addresses fit together

The browser uses `localhost:8000` and `localhost:8080` (`doc_server_url`). Inside Docker, Drupal reaches Docs as
`http://documentserver` (`doc_server_internal_url`) and Docs reaches Drupal as `http://drupal` (`storage_url`).
`trusted_host_patterns` allows both host names. The stand's own settings are in `sites/default/settings.stand.php`,
which is rewritten on every start.
