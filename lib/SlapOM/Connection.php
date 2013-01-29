<?php
namespace SlapOM;

use SlapOM\Exception\Ldap as LdapException;

class Connection
{
    protected $handler;
    protected $host;
    protected $port;
    protected $login;
    protected $password;
    protected $maps = array();
    protected $error;

    public function __construct($host, $login, $password = null, $port = 389)
    {
        $this->login = $login;
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
    }

    public function __destruct()
    {
        if ($this->isOpen())
        {
            ldap_unbind($this->handler);
        }
    }

    public function getMapFor($class, $renew = false)
    {
        if (!isset($this->maps[$class]) or $renew === true)
        {
            $class_name = sprintf("%sMap", $class);
            $this->maps[$class] = new $class_name($this);
        }

        return $this->maps[$class];
    }

    public function search(EntityMap $map, $dn, $filter, $attributes, $limit = 0)
    {
        $ret = @ldap_search($this->getHandler(), $dn, $filter, $attributes, 0, $limit);

        if ($ret === false)
        {
            throw new LdapException(sprintf("Error while filtering dn '%s' with filter '%s'.", $dn, $filter), $this->handler, $this->error);
        }

        return new Collection($this->handler, $ret, $map);
    }

    public function modify($dn, $entry)
    {
        $del_attr = array();
        $mod_attr = array();
        foreach ($entry as $name => $value)
        {
            if (empty($value))
            {
                $del_attr[$name] = array();
            }
            else
            {
                if (is_array($value))
                {
                    $mod_attr[$name] = $value;
                }
                else
                {
                    $mod_attr[$name] = array($value);
                }
            }
        }

        if (count($del_attr) > 0)
        {
            $ret = @ldap_mod_del($this->getHandler(), $dn, $del_attr);
            if ($ret === false)
            {
                throw new LdapException(sprintf("Error while DELETING attributes {%s} in dn='%s'.", join(', ', $del_attr), $dn), $this->handler, $this->error);
            }
        }

        if (count($mod_attr) > 0)
        {
            $ret = @ldap_mod_replace($this->getHandler(), $dn, $mod_attr);
            if ($ret === false)
            {
                throw new LdapException(sprintf("Error while MODIFYING values {%s} in dn='%s'.", join(', ', $mod_attr), $dn), $this->handler, $this->error);
            }
        }

        return true;
    }

    protected function isOpen()
    {
        return !(is_null($this->handler) or $this->handler === false);
    }

    protected function connect()
    {
        $this->handler = ldap_connect($this->host, $this->port);

        ldap_get_option($this->handler,LDAP_OPT_ERROR_STRING,$this->error);
        ldap_set_option($this->handler, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->handler, LDAP_OPT_REFERRALS, 0);

        if (!@ldap_bind($this->handler, $this->login, $this->password))
        {
            throw new LdapException(sprintf("Could not bind to LDAP host='%s:%s' with login='%s'.", $this->host, $this->port, $this->login), $this->handler, $this->error);
        }
    }

    protected function getHandler()
    {
        if (!$this->isOpen())
        {
            $this->connect();
        }

        return $this->handler;
    }
}
