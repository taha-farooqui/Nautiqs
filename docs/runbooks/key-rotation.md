# APP_KEY — client data encryption

Since 27 September 2026, `APP_KEY` is the only key that can read a dealer's
clients: names, emails, phones, addresses, internal notes, the client copy on
every quote, every email sent, and notification text. There is no second copy
of that data in plaintext anywhere.

**Lose the key and that data is gone. Permanently. No support ticket, backup
or database restore brings it back.**

## The one rule

**Never run `php artisan key:generate` on production.** It replaces the key
without asking. Every page that shows a client would then fail with
`Client data could not be decrypted: APP_KEY is not the key it was encrypted
with`, and the data stays unreadable until the old key is put back.

## Where the key must exist

| Copy | Status |
|---|---|
| `/var/www/nautiqs/.env` on the VPS | live |
| Every backup archive (`.env` is included, `include_env=true`) | automatic, twice daily |
| **The owner's password manager** | **must be done by hand, once** |

The third copy is the one that matters: if the VPS and its backups are lost
together, it is the only one left. To copy it:

```bash
ssh ubuntu@51.68.122.41 "sudo grep '^APP_KEY=' /var/www/nautiqs/.env"
```

Store the whole line, including `base64:`. To check a stored copy against the
live one without printing either:

```bash
ssh ubuntu@51.68.122.41 "sudo grep '^APP_KEY=' /var/www/nautiqs/.env | sha256sum | cut -c1-16"
# 0dab10aea5bd5201  <- fingerprint on 27 Sep 2026
```

## If the key was changed by mistake

1. Put the original line back into `.env` (from the password manager, or from
   the `.env` inside any backup archive taken before the change).
2. `sudo -u www-data php artisan config:cache && sudo systemctl reload php8.5-fpm`
3. `sudo -u www-data php artisan pii:scan` — must report `0 Unreadable`.

Anything written *while* the wrong key was in place is encrypted with that key.
`pii:scan` counts it under "Unreadable". Add the wrong key to
`APP_PREVIOUS_KEYS` (below) and it becomes readable again.

## Rotating the key on purpose

Only if the key is believed to have leaked. The app stays up throughout.

1. **Back up.** `sudo -u www-data php artisan backup:run --trigger=manual`
2. **Keep the old key readable.** In `.env`:
   ```
   APP_PREVIOUS_KEYS=base64:OLD…KEY
   APP_KEY=base64:NEW…KEY
   ```
   Generate the new key *without* writing it anywhere:
   `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`
3. `sudo -u www-data php artisan config:cache && sudo systemctl reload php8.5-fpm`
   From here, reads work with either key and new writes use the new one.
4. **Re-encrypt everything with the new key:**
   ```bash
   sudo -u www-data php artisan pii:decrypt --execute --no-interaction
   sudo -u www-data php artisan pii:encrypt --execute
   sudo -u www-data php artisan pii:scan
   ```
   `pii:decrypt` reads with whichever key opens each value; `pii:encrypt` then
   writes with the new one. Between the two commands the data is briefly in
   plaintext — do it at a quiet hour.
5. **Store the new key** in the password manager.
6. Once `pii:scan` shows `0 Unreadable` and a backup has run, remove
   `APP_PREVIOUS_KEYS` and cache the config again.

Sessions are encrypted with the same key. While the old key sits in
`APP_PREVIOUS_KEYS` they keep working; removing it in step 6 logs everyone out
once.

Why not just run `pii:encrypt` after changing the key: a value that the old key
still opens counts as already encrypted, so it would be left under the old key.
The decrypt-then-encrypt pair is what actually moves it.

## The commands

| Command | What it does |
|---|---|
| `pii:scan [--company=ID]` | Read-only. Plaintext, encrypted and unreadable counts per collection, plus any client email found outside the encrypted fields. Exits non-zero while anything is left. |
| `pii:encrypt [--execute] [--company=ID]` | Encrypts what is still plaintext. Dry run without `--execute`; refuses to write without a successful backup in the last hour. Safe to repeat. |
| `pii:decrypt [--execute] [--company=ID]` | Writes everything back as plaintext. The rollback, and step 4 above. |

## What is and is not encrypted

| Encrypted | Readable, on purpose |
|---|---|
| **clients** — first and last name, email, phone, street address, postcode, internal notes, navigation area, current boat | company name, city, country, lead source |
| **quotes** — the same fields in the frozen client copy | company, city, country; everything about the boat and the money |
| **email log** — recipient, name, CC, subject, body, attachment filenames, SMTP error | quote number, type, status, the dealer's reply-to |
| **notifications** — message | title |

Not in scope: dealership accounts (`users`, `companies`) — those are the
platform's own customers, and a login has to be able to find a user by email.

The superadmin dashboard shows a dealer's clients masked (`J••• D•••`,
`j•••@g•••.com`). Encryption does not keep data from the platform itself,
which holds the key; the mask is what does.

## What protects what

- **A leaked database connection string, a raw `mongodump`, an Atlas
  snapshot, a screenshot of a document:** ciphertext only. Covered.
- **A stolen backup archive:** not covered. The archive carries `.env`, so it
  carries the key. It is the price of backups that restore on their own; keep
  downloaded archives somewhere private.
- **Someone with shell access to the VPS:** not covered. They can read `.env`.
