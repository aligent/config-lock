<?php

require_once dirname($argv[0]) . DIRECTORY_SEPARATOR . 'abstract.php';


/**
 * Config_Lock
 *
 * Creates and verifies a configuration lock file based on protected keys defined in local.xml
 *
 * @category  Aligent
 * @package   ConfigLock
 * @author    Andrew Dwyer <andrew@aligent.com.au>
 * @copyright 2014 Aligent Consulting.
 * @link      http://www.aligent.com.au/
 */
class Config_Lock extends Mage_Shell_Abstract
{

    const PROTECTED_STORES_KEY = 'global/aligent_config_lock/protected';
    const CONTACT_EMAILS_KEY = 'global/aligent_config_lock/contacts';
    const BCRYPT_COST = 15;

    public function run() {

        require_once $this->_getRootPath() . 'vendor' . DS . 'ircmaxell' . DS . 'password-compat' . DS . 'lib' . DS . 'password.php';

        if (isset($this->_args['generate'])) {
            $this->debug("Generating lock file");
            $this->generate();

        } else if (isset($this->_args['validate'])) {
            $this->debug("Validating lock file");
            $this->validate();

        } else {
            echo $this->usageHelp();
        }

    }

    /**
     * Validates the file supplied via the file param to ensure the hash values for each param match the current value of
     * the configuration variable in Magento.
     * This cron job will only output a message if there is a validation error or if debug mode is enabled
     */
    protected function validate() {

        $lockFileName = $this->getArg('file');
        $invalidKeys = array();
        if($lockFileName) {

            $inFile = new Varien_Io_File();
            $inFile->open(array(
                'path' => dirname($lockFileName)
            ));
            $lockData = null;
            $lockData = $inFile->read(basename($lockFileName));
            if (!$lockData) {
                $this->fatal("Unable to read supplied file");
            }

            $lockData = json_decode($lockData, true);

            foreach ($lockData['hashes'] as $storeCode => $hashes) {
                $this->debug($storeCode);
                foreach ($hashes as $configKey => $configValueHash) {
                    $store = Mage::getModel("core/store")->load($storeCode, "code");
                    $configValue = Mage::getStoreConfig($configKey, $store);
                    if (password_verify($configValue, $configValueHash)) {
                        $this->debug($configKey . " is valid");
                    } else {
                        $this->error($configKey . " is NOT valid\n");
                        $invalidKeys[] = $configKey;
                    }
                }
            }

            if(count($invalidKeys) > 0) {
                $this->notifyFailures($lockData['emails'], implode(",", $invalidKeys));
                die(1);
            }

        } else {
            $this->fatal('Must supply "file" argument to read lock file');
        }
    }

    /**
     * Notify recipients of failure
     * TODO how do we prevent someone from changing the email settings in Magento to stop emails being sent?
     * We could attempt to send the email via SMTP settings coded directly into lock file? This isn't a big issue as any
     * error returned from the cron job should notify an administrator
     * @param $recipients array Array of email address to notify
     * @param $failures string Failed keys
     */
    protected function notifyFailures($recipients, $failures) {

        $emailTemplate = Mage::getModel('core/email_template')->loadByCode(Mage::getStoreConfig('system/cron/error_email_template'));
        $emailTemplate->setIsPlain(true);
        $emailTemplate->sendTransactional(
            Mage::getStoreConfig('system/cron/error_email_template'),
            Mage::getStoreConfig('system/cron/error_email_identity'),
            $recipients,
            null,
            array('error' => "The following configuration keys have been modified: \n" . $failures, 'schedule' => "Configuration Value Validation")
        );

        if(!$emailTemplate->getSentSuccess()) {
            $this->fatal("Unable to send failure email");
        }

    }

    /**
     * Generates a lock file based on the contents defined in configuration XML and writes to the file defined by the file param
     */
    protected function generate() {

        // load configs to be locked and emails to be notified
        $protectedStores = Mage::getConfig()->getNode(self::PROTECTED_STORES_KEY);
        $contacts = Mage::getConfig()->getNode(self::CONTACT_EMAILS_KEY);

        if(!$protectedStores || !$contacts) {
            $this->fatal("Unable to access configuration properties");
        }

        $protectedStores = $protectedStores->asArray();
        $contacts = $contacts->asArray();

        $lockData = array(
            'emails' => $contacts,
            'hashes' => array());

        foreach ($protectedStores as $storeCode => $keys) {
            $this->debug($storeCode);

            $lockData['hashes'][$storeCode] = array();

            foreach ($keys as $key) {
                $this->debug($key);

                $store = Mage::getModel("core/store")->load($storeCode, "code");
                $configValue = Mage::getStoreConfig($key, $store);
                $this->debug($configValue);

                $hash = password_hash($configValue, PASSWORD_BCRYPT, array("cost" => self::BCRYPT_COST));
                $this->debug($hash);

                $lockData['hashes'][$storeCode][$key] = $hash;
            }
        }

        $data = json_encode($lockData);

        $lockFileName = $this->getArg('file');
        if($lockFileName) {
            $outfile = new Varien_Io_File();

            $outfile->open(array(
                'path' => dirname($lockFileName)
            ));
            if (!$outfile->write(basename($lockFileName), $data)) {
                $this->fatal("Unable to write file");
            }
        } else {
            $this->fatal("Must supply file argument to write lock file");
        }

        $this->info("Lock file successfully written");
    }

    protected function debug($message, $force = false) {
        if($this->getArg('debug') || $force == true) {
            echo $message . "\n";
        }
    }

    protected function info($message) {
        $this->debug($message, true);
    }

    protected function error($message) {
        $this->debug($message, true);
    }

    protected function fatal($message) {
        $this->debug($message, true);
        throw new Exception($message);
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f config_lock.php -- <option> --file <lock_file_path> [--debug]

  generate      Generates a lock file based on the configuration keys defined in local.xml.  Ensure the lock file is written to a directory that can't be read from the web!
  validate      Validates the lock file against the current value of each protected configuration value
  help          This help

USAGE;
    }
}

$shell = new Config_Lock();
$shell->run();

