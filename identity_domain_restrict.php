<?php
/**
 * identity_domain_restrict
 *
 * Allows an identity to be saved only if the domain of its e-mail address matches the domain of the logged-in user's e-mail address (login)
 * The requested hook is `identity_create`. The `identity_update` hook is also wired up: without it the restriction could be bypassed by creating a valid identity and then editing it
 *
 * Configuration (config.inc.php):
 *   $config['identity_domain_restrict_use_login_domain'] = true;   // login domain
 *   $config['identity_domain_restrict_allowed'] = ['mydomain.com'];  // extra domains
 *
 * @license GPL-3.0-or-later
 */
class identity_domain_restrict extends rcube_plugin
{
    /** @var string Tasks the plugin initializes on */
    public $task = 'settings';

    /** @var rcmail */
    private $rc;

    public function init()
    {
        $this->rc = rcmail::get_instance();

        $this->load_config();
        // false: translations available on the PHP side only (not sent to JS)
        $this->add_texts('localization/', false);

        // Requested hook: validate on identity creation.
        $this->add_hook('identity_create', [$this, 'check_identity']);

        // Recommended: same rule on update (prevents bypassing the check).
        $this->add_hook('identity_update', [$this, 'check_identity']);
    }

    /**
     * Validate the identity domain. Used by both identity_create and identity_update. To block saving we set $args['abort'] = true and put an error message in $args['message']
     *
     * @param array $args Hook data ('record' = identity, etc)
     * @return array
     */
    public function check_identity($args)
    {
        $record = isset($args['record']) ? $args['record'] : [];
        $email  = isset($record['email']) ? trim($record['email']) : '';

        // Identity without an e-mail address: nothing to validate.
        if ($email === '') {
            return $args;
        }

        $identity_domain = $this->extract_domain($email);
        $allowed_domains = $this->get_allowed_domains();

        // No reference domain could be determined, it should not happen so it's safer to block the operation
        if (empty($allowed_domains)) {
            $user = $this->rc->user ? $this->rc->user->get_username() : 'unknown';
            rcube::write_log('errors', "identity_domain_restrict: no reference domain for '$user', operation blocked.");
            $args['abort']   = true;
            $args['result']  = false;
            return $args;
        }

        if ($identity_domain === '' || !in_array($identity_domain, $allowed_domains, true)) {
            // Could not determine or validate the domain of the identity e-mail address or it's not one of the allowed domains
            $args['abort']   = true;
            $args['result']  = false;
            $args['message'] = $this->gettext([
                'name' => 'domainnotallowed',
                'vars' => ['domain' => implode(', ', $allowed_domains)],
            ]);
        }

        return $args;
    }

    /**
     * Extract the normalized domain from an e-mail address
     *
     * @param string $email
     * @return string Lowercase (ASCII) domain, '' if invalid
     */
    private function extract_domain($email)
    {
        $pos = strrpos((string) $email, '@');
        if ($pos === false) {
            // There is no '@' character on the e-mail address
            return '';
        }

        return $this->normalize_domain(substr($email, $pos + 1));
    }

    /**
     * Normalize a domain: trim, IDN -> ASCII conversion, lowercase
     *
     * @param string $domain
     * @return string
     */
    private function normalize_domain($domain)
    {
        $domain = trim((string) $domain);
        if ($domain === '') {
            return '';
        }

        $domain = rcube_utils::idn_to_ascii($domain);

        return mb_strtolower($domain);
    }

    /**
     * Build the list of allowed domains for the logged-in user :
     *  - The domain of their login / e-mail address
     *  - Extra domains configured by the administrator via $config['identity_domain_restrict_allowed']
     *
     * @return string[] Normalized, unique domains
     */
    private function get_allowed_domains()
    {
        $domains = [];

        if ($this->rc->config->get('identity_domain_restrict_use_login_domain', true)) {
            $user = $this->rc->user;

            if ($user) {
                // Login domain (username@domain or via mail_domain)
                $domain = $user->get_username('domain');

                // Fall back to the default identity if the login has no domain
                if (empty($domain)) {
                    $default = $user->get_identity();
                    if (!empty($default['email'])) {
                        $domain = $this->extract_domain($default['email']);
                    }
                }

                $domain = $this->normalize_domain($domain);
                if ($domain !== '') {
                    $domains[] = $domain;
                }
            }
        }

        // Extra allowed domains
        $extra = (array) $this->rc->config->get('identity_domain_restrict_allowed', []);
        foreach ($extra as $d) {
            $d = $this->normalize_domain($d);
            if ($d !== '') {
                $domains[] = $d;
            }
        }

        return array_values(array_unique(array_filter($domains)));
    }
}