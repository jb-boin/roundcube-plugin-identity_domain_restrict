# identity_domain_restrict

Roundcube plugin that allows an identity to be saved only if the **domain of
the identity's e-mail address** matches the **domain of the logged-in user's
e-mail address (login)**.

For example, it prevents a user `john@mydomain.com` from creating an identity
`john@gmail.com` or one spoofing another domain.

## How it works

- `identity_create` hook: validation on **creation** (the requested behaviour).
- `identity_update` hook: same rule on **update** — without it the restriction
  could be bypassed (create a valid identity, then edit it).
- The reference domain is derived from the login (`username@domain`, or via
  `mail_domain`), falling back to the default identity.
- Case-insensitive comparison, with IDN -> ASCII conversion.
- On failure the save is aborted (`abort`) and a localized error message is
  shown. Translations are provided for English, French, Spanish and German.

## Installation

1. Copy the `identity_domain_restrict/` folder into Roundcube's `plugins/`.
2. (Optional) `cp config.inc.php.dist config.inc.php` and adjust.
3. Enable the plugin in `config/config.inc.php`:

   ```php
   $config['plugins'] = ['identity_domain_restrict', /* ... */];
   ```

## Configuration

| Key | Default | Purpose |
|---|---|---|
| `identity_domain_restrict_use_login_domain` | `true` | Allow the domain of the user's login |
| `identity_domain_restrict_allowed` | `[]` | Extra allowed domains (aliases, secondary domains) |

## Note on the "fail-open" behaviour

If no reference domain can be determined (login without a domain and
`mail_domain` not set, no default identity), the operation is blocked and the
event is logged to `logs/errors.log`.
