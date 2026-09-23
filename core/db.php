<?php

class db {

    /**
     * Quote the configured database name for use in a DDL statement.
     *
     * The name arrives from the deployment environment rather than from a user
     * request, but it is still interpolated into CREATE DATABASE and USE, which
     * cannot take a bound parameter. Restricting it to identifier characters
     * keeps that interpolation safe no matter how the environment is populated.
     */
    private function databaseName() {

        $name = defined('DB_NAME') ? (string) DB_NAME : '';

        if ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $this->connectionFailure('db_config', 'database name missing or not a valid identifier');
        }

        return $name;
    }

    /**
     * Report a persistence-boundary failure without disclosing how we connect.
     *
     * The category distinguishes a configuration problem from a live connection
     * problem, which is the distinction an operator needs. Neither branch is
     * allowed to carry HOST, USER, PASSWORD, DB_NAME or the PDO DSN into the
     * response — those go nowhere, and only the category reaches the error log.
     */
    private function connectionFailure($operation, $detail) {

        error_log('ticket99 operation=' . $operation . ' outcome=failure detail=' . $detail);

        die(json_encode(array('outcome' => false, 'message' => 'Unable to connect')));
    }

    public function getDBH() {

        static $DBH = null;

        if (is_null($DBH)) {
            $database_name = $this->databaseName();

            try{
                $DBH = new PDO('mysql:host=' . HOST .';port=' . PORT . ';', USER, PASSWORD,
                                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

                //create database if it does not exist.
                $DBH->query("CREATE DATABASE IF NOT EXISTS `" . $database_name . "`");
                $DBH->query("use `" . $database_name . "`");
                $DBH->query("CREATE TABLE IF NOT EXISTS `admin_settings` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `self_delete_account` varchar(1) NOT NULL,
                                `allow_user_sign_in` varchar(1) NOT NULL,
                                `allow_new_user_register` varchar(1) NOT NULL,
                                `enable_protection` varchar(1) NOT NULL,
                                PRIMARY KEY (`id`)
                              ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
                              
                              INSERT INTO `admin_settings` (`id`, `self_delete_account`, `allow_user_sign_in`, `allow_new_user_register`, `enable_protection`) VALUES
                              (1, '1', '1', '1', '1');
 
                              CREATE TABLE IF NOT EXISTS `tickets` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `uniqid` varchar(20) NOT NULL,
                                `user` int(11) NOT NULL,
                                `title` varchar(250) NOT NULL,
                                `init_msg` text NOT NULL,
                                `department` int(11) NOT NULL,
                                `date` varchar(250) NOT NULL,
                                `last_reply` int(11) NOT NULL,
                                `user_read` int(11) NOT NULL,
                                `admin_read` int(11) NOT NULL,
                                `resolved` int(11) NOT NULL,
                                PRIMARY KEY (`id`)
                              ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=20 ;
                              
                              CREATE TABLE IF NOT EXISTS `ticket_replies` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `user` int(11) NOT NULL,
                                `text` text NOT NULL,
                                `ticket_id` text NOT NULL,
                                `date` varchar(20) NOT NULL,
                                PRIMARY KEY (`id`)
                              ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=41 ;
                              
                              CREATE TABLE IF NOT EXISTS `departments` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `name` varchar(50) NOT NULL,
                                `hidden` int(1) NOT NULL,
                                PRIMARY KEY (`id`)
                              ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;
                              
                              INSERT INTO `departments` (`id`, `name`, `hidden`) VALUES
                              (1, 'Test Department 1', 0),
                              (2, 'Test Department 2', 0),
                              (3, 'Test Department 3', 0),
                              (4, 'Test Department 4', 0);
                              
                              CREATE TABLE IF NOT EXISTS `registrations` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `ip` varchar(100) NOT NULL,
                                `date` varchar(50) NOT NULL,
                                PRIMARY KEY (`id`)
                              ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;
                              
                              CREATE TABLE IF NOT EXISTS `users` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `email` varchar(250) NOT NULL,
                                `password` varchar(250) NOT NULL,
                                `sign_up_date` varchar(250) NOT NULL,
                                `nick_name` varchar(250) NOT NULL,
                                `user_group` int(11) NOT NULL,
                                `last_login` varchar(250) NOT NULL,
                                `url` varchar(270) NOT NULL,
                                `allowed` int(11) NOT NULL,
                                `most_recent_ip` varchar(100) NOT NULL,
                                PRIMARY KEY (`id`)
                              ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=13 ;
                              
                              ALTER TABLE `users` AUTO_INCREMENT = 1;
                              
                              /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
                              /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
                              /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
                          ");
                    
            }
            catch(PDOException $ex){
                // $ex->getMessage() can contain the host, port and user, so it is
                // deliberately not propagated — only the failure category is.
                $this->connectionFailure('db_connect', 'database connection refused or unavailable');
            }
            
        }
        return $DBH;
    
    }
    
    public function site_settings($setting) {
        
        $_link = $this->getDBH();
        
        $query = $_link->query('SELECT * FROM `admin_settings` WHERE 1');
        $query->execute();
        
        $result = $query->fetch(PDO::FETCH_ASSOC);
        
        return $result[$setting];
        
    }
    
}