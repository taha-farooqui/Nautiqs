# Runbook: restoring Nautiqs from a backup

Follow this top to bottom. It has been executed end to end against production
(19 Sep 2026) — 25 collections and 3 568 documents restored with every count
matching the live database.

**Read step 5 before you run step 4.** Pointing a restore at the wrong cluster,
or caching config against the wrong `.env`, is the one mistake here that is
hard to undo — it is what caused the June 2026 login outage.

---

## What an archive is

`nautiqs-YYYYMMDD-HHMMSS.tar.gz`, taken at 00:00 and 12:30 Paris time, holding:

| Inside | What it is |
|---|---|
| `db.archive.gz` | `mongodump` of the whole database, gzipped |
| `storage/` | `storage/app/public` — the dealership logos printed on every quote PDF |
| `.env` | server configuration, **including `APP_KEY`** |
| `manifest.json` | when it was taken, which database, which commit, what it contains |

Two things follow from that:

- **Archives are secret.** They carry every dealership's client data and live
  credentials. Keep downloaded copies somewhere private; never put one in a
  shared drive, a ticket, or an email.
- **Restoring does not need Nautiqs.** The dump is a plain MongoDB archive, so
  a restore only needs `mongorestore`. It does not matter whether the app runs,
  or whether this code still exists.

---

## Where archives are

- **On the server:** `/var/www/nautiqs/storage/app/backups`, owner `www-data`,
  directory `0700`, files `0600`. Not served by nginx — the only way to fetch
  one over HTTP is the superadmin Backups page.
- **Off the server:** whatever the superadmin has downloaded from that page.
  The server keeps the last 30 days; older archives are handed over there and
  deleted only after they have been downloaded.

---

## 1. Get the archive

From the admin area: **Backups → Download** on the row you want.

Or straight off the server:

```bash
ssh ubuntu@51.68.122.41
sudo ls -lt /var/www/nautiqs/storage/app/backups/
sudo cp /var/www/nautiqs/storage/app/backups/nautiqs-YYYYMMDD-HHMMSS.tar.gz /tmp/
sudo chown "$USER" /tmp/nautiqs-YYYYMMDD-HHMMSS.tar.gz
```

## 2. Unpack and check what you have

```bash
mkdir -p /tmp/restore && cd /tmp/restore
tar -xzf /tmp/nautiqs-YYYYMMDD-HHMMSS.tar.gz
cat manifest.json
```

`manifest.json` tells you the date, the source database, the commit that was
deployed, and whether files and `.env` are included. If `db.archive.gz` is
missing or zero bytes, stop — use an older archive.

## 3. Decide where it is going

| Target | When | Risk |
|---|---|---|
| A scratch database on the same cluster | Drills, inspecting old data | None |
| `nautiqs-dev` cluster | Staging | None to production |
| `nautiqs` on the production cluster | A real disaster | **Destroys current data** |

For anything other than a genuine disaster, restore into a scratch database:

```bash
mongorestore --uri="<connection string>" --gzip --archive=db.archive.gz \
  --nsFrom='nautiqs.*' --nsTo='nautiqs_restore_drill.*'
```

…and drop it when finished.

## 4. Restore

`--drop` empties each collection before refilling it. On the production
database that discards everything written since the archive was taken.

```bash
mongorestore --uri="<connection string>" --gzip --archive=db.archive.gz --drop
```

Put the connection string in a `0600` config file rather than on the command
line, where it sits in `ps` output for every process on the box:

```bash
printf 'uri: %s\n' "$MONGODB_URI" > /tmp/mongo.conf && chmod 600 /tmp/mongo.conf
mongorestore --config=/tmp/mongo.conf --gzip --archive=db.archive.gz --drop
rm -f /tmp/mongo.conf
```

Restore the uploaded logos too, or every PDF loses its letterhead:

```bash
sudo cp -r storage/* /var/www/nautiqs/storage/app/public/
sudo chown -R www-data:www-data /var/www/nautiqs/storage/app/public
```

## 5. `.env` — read this before caching config

Only restore `.env` if the server's own copy is lost or you are rebuilding on a
new machine.

**Check which cluster it points at before doing anything else.** A `.env` from
an archive points at whichever cluster was live when it was taken. Caching
config with the wrong pointer silently moves the whole application to a
different database — that is exactly what took logins down in June 2026, and it
looks like a data-loss incident rather than a configuration mistake.

```bash
grep -c 'nautiqs-prod.weqivcf' /var/www/nautiqs/.env     # must be 1
php artisan config:cache
```

`APP_KEY` lives in this file. Once client data is encrypted at rest, a database
restored without the matching key is unreadable — the archive is only a
complete disaster-recovery artefact because `.env` is in it.

## 6. Bring the app back and verify

```bash
cd /var/www/nautiqs
sudo php artisan config:cache && sudo php artisan route:cache && sudo php artisan view:cache
sudo systemctl reload php8.5-fpm
curl -s -o /dev/null -w '%{http_code}\n' https://app.nautiqs.fr/login   # expect 200
```

Then check by hand, in this order — each one catches a different kind of
partial restore:

1. Log in as the superadmin.
2. Open a dealership and confirm its quotes are listed.
3. Open one quote and generate its PDF: the totals must match, and the logo
   must appear (that proves `storage/` came back too).
4. Compare a collection count against the manifest's date expectations.

---

## Drill: prove the archives still restore

Do this quarterly, and after any Atlas version upgrade — `mongodump` and the
server drifting apart is the realistic way a backup quietly stops working.

```bash
mongorestore --config=/tmp/mongo.conf --gzip --archive=db.archive.gz \
  --nsFrom='nautiqs.*' --nsTo='nautiqs_restore_drill.*' --drop
# compare collection and document counts against nautiqs, then:
# db.getSiblingDB('nautiqs_restore_drill').dropDatabase()
```

Nothing touches production. If counts match and a sample quote reads correctly,
the archive is good.

---

## When backups themselves go wrong

| Symptom | Cause | What to do |
|---|---|---|
| Backups page shows the last backup in red | No successful run for 26 h, so roughly two scheduled runs were missed | Check the cron: `sudo crontab -u www-data -l` should run `schedule:run` every minute. Then `sudo -u www-data php artisan backup:run` and read the error |
| "mongodump was not found" | MongoDB Database Tools missing after a server rebuild | Reinstall (see below), or set `BACKUP_MONGODUMP` to the absolute path |
| "Not enough disk space" | Disk filling up | `df -h`, clear old logs, then download and free old archives from the Backups page |
| A run is stuck on "Running" | Process killed mid-backup, lock still held | It expires after 15 minutes. The archive is removed automatically; no partial file is ever left behind |
| Nothing is deletable on the page | Nothing has been downloaded yet | Download the old archives first. Nothing is ever deleted before a copy exists off the server |

Reinstalling the tools:

```bash
V=100.12.2
curl -sSLO "https://fastdl.mongodb.org/tools/db/mongodb-database-tools-ubuntu2404-x86_64-${V}.tgz"
tar xzf mongodb-database-tools-ubuntu2404-x86_64-${V}.tgz
sudo install -m 0755 mongodb-database-tools-ubuntu2404-x86_64-${V}/bin/mongodump    /usr/local/bin/
sudo install -m 0755 mongodb-database-tools-ubuntu2404-x86_64-${V}/bin/mongorestore /usr/local/bin/
```

---

## What the schedule does on its own

| When (Paris) | What | Deletes anything? |
|---|---|---|
| 00:00 | `backup:run` | no |
| 12:30 | `backup:run` | no |
| 03:00 | `backup:prune` | Only archives that are past 30 days **and** were downloaded more than 2 days ago. Never the newest 4. |

Deletion is deliberately hard to trigger. An archive must be past retention,
must already have been downloaded, and must not be among the newest few — and
the nightly run waits a further couple of days after the download, because
serving a file is not proof it arrived. The **Free up space** button on the
Backups page is the explicit human version and skips only that last wait.
