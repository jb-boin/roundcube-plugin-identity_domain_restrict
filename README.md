# identity_domain_restrict

Roundcube plugin that allows an identity to be saved only if the **domain of
the identity's e-mail address** matches the **domain of the logged-in user's
e-mail address (login)** or one of the domains listed in $config['identity_domain_restrict_allowed'].

It allows more granularity than $config['identities_level'] currently permits.

For example, it prevents a user `john@mydomain.com` from creating an identity
`john@gmail.com` but allows `alias@mydomain.com`.

## How it works

- `identity_create` hook : Validation on the **creation** of a new identity
- `identity_update` hook : Same rule on the **update** of an existing identity
- The reference domain is derived from the login (`username@domain`, or via
  `mail_domain`), falling back to the default identity
- Case-insensitive comparison, with IDN -> ASCII conversion
- On failure, the save is aborted and a localized error message is
  shown (translations are currently provided for English, French, Spanish and German)

## Installation

1. Copy the `identity_domain_restrict/` folder into Roundcube's `plugins/`
2. (Optional) `cp config.inc.php.dist config.inc.php` and adjust
3. Enable the plugin by adding it to the 'plugins' array in `config/config.inc.php` :
   ```php
   $config['plugins'] = [/* ... */, 'identity_domain_restrict'];
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
