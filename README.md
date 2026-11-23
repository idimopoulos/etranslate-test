# eTranslation test harness

A small plain-PHP ddev app that talks to the EC **eTranslation REST v2** API the same way
Joinup's `tmgmt_ec_etranslation` module (plugin `ec_etranslation_rest_v2`) does. Use it to send text,
receive the asynchronous callbacks, and inspect both the **raw** data and the **decoded** string.

## Quickstart

Requirements: [DDEV](https://ddev.com) and [cloudflared](https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/downloads/)
on the host. No Cloudflare account is needed.

1. **Clone the repository**
   ```bash
   git clone <repository-url> etranslate_test
   cd etranslate_test
   ```
2. **Create and fill in the environment file**
   ```bash
   cp .ddev/.env.example .ddev/.env
   ```
   Set `ETRANSLATION_USERNAME` and `ETRANSLATION_PASSWORD`, and set `APP_SECRET` to a random value
   (`openssl rand -hex 32`).
3. **Start the project and run the setup**
   ```bash
   ddev start
   ddev setup
   ```
   `ddev setup` installs the Composer dependencies, creates the database tables and starts a temporary
   public tunnel. It prints the tunnel URL, for example:
   ```
   Callback base URL: https://some-random-words.trycloudflare.com
   ```
   That URL becomes the default "Callback base URL" in the send form, so you don't need to copy it anywhere.
4. **Open the site** at https://etranslate-test.ddev.site (or run `ddev launch`) and send a text.
   You land on its result page (`/requests/{id}`), which refreshes until eTranslation's callback arrives.
   Every result is listed under **Results** in the header, and the latest ones also appear below the send form.

If you edit `.ddev/.env` later, run `ddev restart`. Then run `ddev tunnel start` again, because a restart
stops the tunnel.

## Configuration (`.ddev/.env`)

| Env var | Meaning |
|---|---|
| `ETRANSLATION_USERNAME` / `ETRANSLATION_PASSWORD` | HTTP Basic credentials for language-tools.ec.europa.eu |
| `ETRANSLATION_CALLER` | `callerInformation.username` (max 10 chars, default `eyi`, as in the module) |
| `CALLBACK_BASE_URL` | Optional fixed public URL. Used only when no `ddev tunnel` is running |
| `APP_SECRET` | Key for the HMAC token in callback URLs |

## Callback tunnel

eTranslation POSTs results to a callback URL, so the site has to be reachable from the internet.
`ddev tunnel` wraps `ddev share --provider=cloudflared` without `--tunnel`/`--hostname`, so each start gets
a new random **Cloudflare Quick Tunnel** URL (`*.trycloudflare.com`). Named tunnels in `~/.cloudflared`
are never used or changed.

```bash
ddev tunnel start    # start, or restart with a fresh URL
ddev tunnel status   # show the current URL
ddev tunnel stop     # stop it
```

- The current URL is stored in `var/callback_base_url`, and the send form reads it from there.
  While a tunnel is running it takes precedence over `CALLBACK_BASE_URL`.
- The tunnel log is in `var/tunnel.log`.
- Each request stores its full callback URL when it is sent. If you restart the tunnel before eTranslation
  calls back, that callback is lost because the old URL no longer exists. Send the text again.
- Quick Tunnels are Cloudflare's no-guarantee testing option, and the URL changes every time. The module's
  docs say the eTranslation team discourages ngrok, which is why this uses cloudflared.

## Pages

| Route | What it does |
|---|---|
| `GET /` | **New translation**: the send form, with the 10 most recent results below it. Domain and languages are searchable dropdowns, and the target list only shows pairs the chosen domain supports |
| `POST /send` | Stores the request, POSTs to `askTranslate`, stores the raw response, then redirects to the result page |
| `GET /requests` | **Results**: every request with its status, original text and translation. Click a row to open it |
| `GET /requests/{id}` | **Result page**: original and translation side by side, the raw callbacks, and the request that was sent. Refreshes every 10 s while waiting |
| `POST /callback/{id}/{hash}` | Success callback: stores raw headers and body, decodes and stores the translation, returns 204 |
| `POST /error/{id}/{hash}` | Error callback: stores raw body and error fields, returns 204 |
| `GET /domains` | Live `getDomains`: one card per domain, plus a searchable, sortable table of language pairs and their variants (`LLM`, `GLS`…). The raw JSON is collapsed below. Also confirms the credentials work |

## Behaviour mirrored from the module

- Payload choice: HTML is sent as `documentToTranslate` (base64, `html`), text over 1000 chars as `documentToTranslate` (base64, `txt`), anything else as `textToTranslate`.
- `[notranslate]` wrapping from regex patterns (port of `preserveNonTranslatables()`). The form is prefilled
  with Joinup's patterns from `develop` (`config/sync/tmgmt.translator.ec_etranslation.yml`: `<pre>`, `<code>`,
  Drupal `@/!/%` placeholders and `[tokens]`, with clean-up enabled). They are defined in
  `Config::DEFAULT_NOTRANSLATE_PATTERNS`.
- `externalReference` is the local request id. Callback and error URLs carry a 43-char HMAC token.
- Decoding: if the *original* text was HTML or over 1000 chars, the callback value (`translatedText ?? result`) is base64-decoded, then `[notranslate]` tags are stripped.

## Non-translatable modes

The send form has two cases (plus **Both**, which sends the same text once per mode and opens the two results side by side):

| Mode | Sent | On receipt |
|---|---|---|
| **Wrap** (current release) | `[notranslate]<code>…</code>[/notranslate]` | `[notranslate]` tags stripped |
| **Placeholders** (branch `notranslate-placeholders`) | `[notranslate]NTPH1[/notranslate]` | `unmask()` restores each part from the source text; unresolved placeholders are flagged |

`src/NonTranslatableParts.php` is a verbatim copy of the module's class (only the namespace differs). Re-copy it when
the branch changes. The result page stores the input text, the text as sent, the decoded reply with markers or placeholders,
and the final string. When the module would decode the reply differently from how it was sent, the page shows a note.

After changing the decoding logic, recompute the stored callbacks from their raw bodies:

```bash
ddev exec php bin/reprocess.php        # all
ddev exec php bin/reprocess.php 47 48  # specific requests
```

## Storage

MariaDB tables are created automatically on first request (`schema.sql`):

- `translation_request`: what was sent, the HTTP status and the raw askTranslate body (the request id or a negative error code).
- `translation_callback`: every callback that passes the token check, with its raw headers and body, the parsed fields, `decoded_result` (`result` base64-decoded, or `translatedText` as is, with `[notranslate]` tags kept) and `translated_text` (the final string, after the module's logic).

```bash
ddev mysql -e "SELECT id, kind, raw_body, translated_text FROM translation_callback"
```
