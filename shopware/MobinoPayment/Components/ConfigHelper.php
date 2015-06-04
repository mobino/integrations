<?php
/**
 * This class provides methods to alter and save plugin config entries
 */
class Shopware_Plugins_Frontend_MobinoPayment_Components_ConfigHelper
{
    /**
     * Creates the table all data is saved in
     */
    private function _createConfigTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS mobino_config_data("
               . "id int(1) NOT NULL UNIQUE,"
               . "apikey varchar(255),"
               . "apisecret varchar(255),"
               . "mobinoDebugging tinyint(1) NOT NULL,"
               . "mobinoLogging tinyint(1) NOT NULL"
               . ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
               . "INSERT IGNORE INTO mobino_config_data ("
               . "id, apikey, apisecret, "
               . "mobinoDebugging, mobinoLogging) VALUES("
               . "1, NULL,NULL,0,0);";
        Shopware()->Db()->query($sql);
    }

    /**
     * Saves the config properties into the db
     */
    public function persist()
    {
        $this->_createConfigTable();
        $swConfig = Shopware()->Plugins()->Frontend()->MobinoPayment()->Config();
        $apikey = trim($swConfig->get("apikey"));
        $apisecret = trim($swConfig->get("apisecret"));
        $debuggingFlag = $swConfig->get("mobinoDebugging") == true;
        $loggingFlag = $swConfig->get("mobinoLogging") == true;

        $sql = "UPDATE mobino_config_data SET"
               . "`mobinoDebugging` = ?,"
               . "`mobinoLogging` = ?"
               . "WHERE id = 1";
        Shopware()->Db()->query(
        $sql,
            array(
                $debuggingFlag ? 1 : 0,
                $loggingFlag ? 1 : 0
            )
        );

        if ($apikey != '' && $apikey != null) {
            $sql = "UPDATE mobino_config_data SET"
                   . "`apikey` = ?"
                   . "WHERE id = 1";
            Shopware()->Db()->query(
            $sql,
                array(
                    $apikey
                )
            );
        }

        if ($apisecret != '' && $apisecret != null) {
            $sql = "UPDATE mobino_config_data SET"
                   . "`apisecret` = ?"
                   . "WHERE id = 1";
            Shopware()->Db()->query(
            $sql,
                array(
                    $apisecret
                )
            );
        }
    }

    /**
     * Restores all configurations from a past installation
     * @return mixed
     */
    public function loadData()
    {
        $this->_createConfigTable();
        $sql = "Select * FROM mobino_config_data WHERE id = 1;";
        $result = Shopware()->Db()->fetchAll($sql);
        return $result[0];
    }
}