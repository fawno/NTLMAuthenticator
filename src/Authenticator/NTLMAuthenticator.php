<?php
    declare(strict_types=1);

    namespace Fawno\NTLM\Authenticator;

    use Authentication\Authenticator\AbstractAuthenticator;
    use Authentication\Authenticator\PersistenceInterface;
    use Authentication\Authenticator\Result;
    use Authentication\Authenticator\ResultInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;

    class NTLMAuthenticator extends AbstractAuthenticator implements PersistenceInterface {
        /**
         * Default config for this object.
         * - `sessionKey` Session key.
	     * - `domains` Array with domain(s) config
	     *
         * @var array
         */
        protected $_defaultConfig = [
            'sessionKey' => 'Auth',
            'domains' => [],
        ];

        /**
         * Authenticate a user using NTLM server data.
	     * Server params:
	     * - `AUTH_TYPE` NTLM
	     * - `REMOTE_USER` domain\username
         *
         * @param \Psr\Http\Message\ServerRequestInterface $request The request to authenticate with.
         * @return \Authentication\Authenticator\ResultInterface
         */
        public function authenticate (ServerRequestInterface $request) : ResultInterface {
            $user = false;

            $server = $request->getServerParams();
            $auth_type = $server['AUTH_TYPE'] ?? '';
            $remote_user = $server['REMOTE_USER'] ?? '';

            if ($auth_type !== 'NTLM') {
                return new Result(null, Result::FAILURE_CREDENTIALS_MISSING);
            }

            $user = $this->get_user_data($remote_user);

            if (empty($user)) {
                return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND, $this->_identifier->getErrors());
            }

            return new Result($user, Result::SUCCESS);
        }

        protected function get_user_data (string $auth_user) {
            $user = [
                'domain' => dirname($auth_user),
                'username' => basename($auth_user),
                'displayname' => dirname($auth_user),
                'dn' => [],
                'memberof' => [],
            ];

            $domain = $this->getConfig('domains.' . $user['domain']);
            if ($domain) {
                $ldap = ldap_connect($domain['ldap']['srv']);
                ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_bind($ldap, base64_decode($domain['ldap']['user']), base64_decode($domain['ldap']['pass']));

                $attributes = ['displayname', 'samaccountname', 'memberof'];
                $filter = '(samaccountname=' . $user['username'] . ')';
                $result = ldap_search($ldap, $domain['ldap']['dn'], $filter, $attributes);

                $entries = ldap_get_entries($ldap, $result);
                if (!$entries['count']) {
                    $result = ldap_search($ldap, $domain['ldap']['dn_users'], $filter, $attributes);
                    $entries = ldap_get_entries($ldap, $result);
                }
                if (!$entries['count']) {
                    return false;
                }

                ldap_unbind($ldap);

                $user['displayname'] = $entries[0]['displayname'][0] ?? $user['username'];

                if (isset($entries[0]['dn'])) {
                    foreach(explode(',', $entries[0]['dn']) as $dn_entry) {
                        list($dn_key, $dn_value) = explode('=', $dn_entry);
                        if ($dn_key == 'OU') {
                            $user['dn'][$dn_key][$dn_value] = $dn_value;
                        } else {
                            $user['dn'][$dn_key][] = $dn_value;
                        }
                    }
                }

                if (isset($entries[0]['memberof'])) {
                    foreach ($entries[0]['memberof'] as $key => $memberof) {
                        if (is_int($key)) {
                            foreach(explode(',', $memberof) as $memberof_entry) {
                                list($memberof_key, $memberof_value) = explode('=', $memberof_entry . '=');
                                if ($memberof_key == 'CN') $user['memberof'][$memberof_value] = $memberof_value;
                            }
                        }
                    }
                }

                if (is_array($user['displayname'])) {
                    if (!empty($user['dn']['CN'][0])) {
                        $user['displayname'] = $user['dn']['CN'][0];
                    } else {
                        $user['displayname'] = implode('/', $user['username']);
                    }
                }

                $user['config'] = $domain['config'] ?? null;
            }

            return $user;
        }

        /**
         * @inheritDoc
         */
        public function persistIdentity (ServerRequestInterface $request, ResponseInterface $response, $identity) : array {
            $sessionKey = $this->getConfig('sessionKey');
            /** @var \Cake\Http\Session $session */
            $session = $request->getAttribute('session');

            if (!$session->check($sessionKey)) {
                $session->renew();
                $session->write($sessionKey, $identity);
            }

            return [
                'request' => $request,
                'response' => $response,
            ];
        }

        /**
         * @inheritDoc
         */
        public function clearIdentity (ServerRequestInterface $request, ResponseInterface $response) : array {
            $sessionKey = $this->getConfig('sessionKey');
            /** @var \Cake\Http\Session $session */
            $session = $request->getAttribute('session');
            $session->delete($sessionKey);
            $session->renew();

            return [
                        'request' => $request->withoutAttribute($this->getConfig('identityAttribute')),
                        'response' => $response,
            ];
        }
    }
