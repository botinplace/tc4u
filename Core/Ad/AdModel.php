<?php

namespace Core\Ad

class AdModel {
    private $ldapConnection;

    public function __construct($ldapHost, $ldapPort) {
        $this->ldapConnection = ldap_connect($ldapHost, $ldapPort);
        ldap_set_option($this->ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
    }

    public function authenticate($username, $password) {
        $bind = @ldap_bind($this->ldapConnection, $username, $password);
        return $bind;
    }

    public function search($baseDn, $filter) {
        $result = ldap_search($this->ldapConnection, $baseDn, $filter);
        return ldap_get_entries($this->ldapConnection, $result);
    }

    public function __destruct() {
        ldap_unbind($this->ldapConnection);
    }
}