# Leiterrunde – Finanzen (Finances)

Module app of the [Leiterrunde](https://github.com/ollimaz/leiterrunde) suite:
bank accounts, transactions, payment obligations and expenses for the leader
team.

> **Requires** the core app [`leiterrunde`](https://github.com/ollimaz/leiterrunde)
> to be enabled first.

## Features

- **Bank accounts** — multiple accounts with configurable transaction parsers
- **Transactions** — account statements via CSV/camt import and automatic IMAP mailbox polling (`ImapPollJob`) for bank e-mails
- **Payment obligations** — per-person payment obligations (e.g. event fees) with reminders via `ObligationCheckJob`; reference format `EVT<event_id>-REG<registration_id>` for website registrations
- **Expenses** — expense claims with receipt attachments (core document storage), approval/rejection and payout tracking
- **Dashboard widget** — open expenses on the Nextcloud dashboard

## Requirements

- Nextcloud 29 – 36
- Enabled [`leiterrunde`](https://github.com/ollimaz/leiterrunde) app
- For IMAP polling: a reachable IMAP mailbox configured in the admin settings

## Installation

```bash
cd /path/to/nextcloud/apps
git clone https://github.com/ollimaz/leiterrunde_finanzen.git
occ app:enable leiterrunde          # core, once
occ app:enable leiterrunde_finanzen
```

## API

Base path `/apps/leiterrunde_finanzen/api` — authentication as in the core
app (session + `requesttoken`, or HTTP Basic + `OCS-APIRequest: true`).

| Verb | Paths |
|---|---|
| GET | `/accounts`, `/accounts/parsers`, `/transactions`, `/transactions/{id}`, `/obligations`, `/obligations/{id}`, `/expenses`, `/expenses/categories`, `/expenses/{id}`, `/expenses/{id}/file`, `/settings` |
| POST | `/accounts`, `/transactions/import`, `/transactions/{id}/assign`, `/obligations`, `/expenses`, `/expenses/{id}/approve`, `/expenses/{id}/reject`, `/expenses/{id}/pay`, `/imap/poll`, `/imap/test` |
| PUT | `/accounts/{id}`, `/obligations/{id}`, `/settings` |
| DELETE | `/accounts/{id}`, `/obligations/{id}`, `/expenses/{id}` |

## Localization

Source strings are English; the maintained translation is German (informal
"du"). Only `l10n/de.json` is maintained — the generated `de.js`,
`de_DE.json` and `de_DE.js` are built with
[`l10n-tools`](https://github.com/ollimaz/leiterrunde/tree/main/l10n-tools)
from the core app.

## Development

```bash
cd tests && phpunit -c phpunit.xml
```

## License

[AGPL-3.0-or-later](LICENSE)
