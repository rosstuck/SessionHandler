<?php
/**
 * @package D
 * @subpackage D_SessionDriver
 */


/**
 * Mysql session driver
 * The expected table structure:
    CREATE TABLE `phpsession` (
        `id` VARCHAR(32) NOT NULL,
        `created` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        `updated` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        `data` MEDIUMBLOB NULL,
        PRIMARY KEY (`id`),
        INDEX `updated` (`updated`)
    ) COLLATE='latin1_general_cs' ENGINE=InnoDB
 */
class D_SessionDriver_Mysql extends D_SessionDriver_Abstract
{
    /**
     * Mysql connection resource
     *
     * @var Resource
     */
    protected $_conn = NULL;

    /**
     * The config
     *
     * @var array
     */
    protected $_config = array(
        'table_name'  => 'phpsession',
        'username'    => '',
        'password'    => '',
        'database'    => '',
        'hostname'    => 'localhost'
    );

    /**
     * The data for the current request,
     * needed to figure some stuff out.
     *
     * @var array
     */
    protected $_data = array( 'id' => NULL );


    /**
     * Construct, config options are:
     * - table_name
     * - username
     * - password
     * - database
     * - hostname (Include the port if you have a non-default one)
     *
     * @param array $config
     * @return
     */
    public function __construct( $config )
    {
        $this->_config = array_merge($this->_config, $config);
    }


    /**
     * Open up the back-end
     *
     * @param string $savePath
     * @param string $sessionName
     * @return boolean
     */
    public function open($savePath, $sessionName)
    {
        $this->_conn = mysql_connect(
            $this->_config['hostname'],
            $this->_config['username'],
            $this->_config['password']
        );

        if ($this->_conn) {
            if ( ! mysql_select_db($this->_config['database'], $this->_conn)) {
                throw new D_Exception_Runtime('Unable to init mysql backend.');
            }
        }
    }


    /**
     * Close the back-end
     *
     * @return boolean
     */
    public function close()
    {
        if (is_resource($this->_conn)) {
            return mysql_close( $this->_conn );
        }
        return false;
    }


    /**
     * Read the session from our back-end
     *
     * @param string $id
     * @return string
     */
    public function read($id)
    {
        $sql = sprintf(
            'SELECT data FROM %s WHERE id = "%s" LIMIT 1',
            $this->_config['table_name'],
            mysql_real_escape_string($id)
        );

        $query = mysql_query($sql, $this->_conn);
        if ($query === false) {
            throw new D_Exception_Runtime('DB error reading session: '.mysql_error());
        }

        $this->_data = mysql_fetch_assoc($query);
        return (string) $this->_data['data'];
    }


    /**
     * write the session to our backend
     *
     * @param string $id
     * @param string $payload
     * @return boolean
     */
    public function write($id, $payload)
    {
        $sql = sprintf(
            'UPDATE %s
            SET  id = "%s",
                updated = NOW(),
                data = "%s"
            WHERE id = "%s"
            LIMIT 1',
            $this->_config['table_name'],
            mysql_real_escape_string($id),
            mysql_real_escape_string($payload),
            mysql_real_escape_string($this->_handler->getOldSID())
        );

        $retVal = mysql_query($sql, $this->_conn);
        if ($retVal && (mysql_affected_rows($this->_conn) === 0)) {
            $sql = sprintf(
                'INSERT INTO %s (id, created, updated, data)
                 VALUES (
                    "%s",
                    NOW(),
                    NOW(),
                    "%s"
                 )',
                $this->_config['table_name'],
                mysql_real_escape_string($id),
                mysql_real_escape_string($payload)
            );

            return (bool) mysql_query($sql, $this->_conn);
        }

        return (bool) $retVal;
    }


    /**
     * Delete a session from the backend
     *
     * @param string $id

     * @return boolean
     */
    public function destroy($id)
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE id = "%s"',
            $this->_config['table_name'],
            mysql_real_escape_string($id)
        );

        return (bool) mysql_query($sql, $this->_conn);
    }


    /**
     * Garbage collection on the backend.
     *
     * @param int $ttl
     * @return boolean
     */
    public function gc($ttl)
    {
        $sql = sprintf(
            'DELETE FROM %s WHERE updated < "%s"',
            $this->_config['table_name'],
            date('Y-m-d H:i:s', (time() - $ttl))
        );

        return (bool) mysql_query($sql, $this->_conn);
    }
}