Magento Config Lock
===================

Hashes the value of specified config settings and notifies a list of email recipients if they have been changed.

How to install
--------------

1. Composer or modman install this module.
2. Edit your local.xml file using the example below.
3. Generate a lock file using the following command.
  1. `php shell/config_lock.php generate --file <CONFIG_FILE_NAME>`
4. Create a cron job to run the following command on a schedule of your choice.
  1. `php shell/config_lock.php validate --file <CONFIG_FILE_NAME>`
  2. The validate command will return a status code of 0 if the file is valid, otherwise 1 will be returned and all recipients will be notified via email

Configuration Example
---------------------

Add the following to your local.xml file to monitor specific config settings.

```
<config>
    <global>
        ...
        <aligent_config_lock>
            <contacts>
                <admin>admin@email.com</admin>
            </contacts>
            <protected>
                <store_code_A>
                    <pbridge_merch_key>payment/pbridge/merchantkey</pbridge_merch_key>
                    <pbridge_trans_key>payment/pbridge/transferkey</pbridge_trans_key>
                </store_code_A>
                <store_code_B>
                    <authorizenet_key>payment/authorizenet/trans_key</authorizenet_key>
                </store_code_B>
            </protected>
        </aligent_config_lock>
    </global>
</config>
```

Cron Job Example
----------------

The following cron job runs every 5 minutes and verified that the config values haven't changed.
Any validation errors will result in the email recipients coded into the lock file being notified and the cron job returning a failure status code.

To manually run the command to verify that it's working, run `echo $?` immediately after to confirm that 0 is returned.  If not, the script should print an error message and return a non 0 status code.

```
MAILTO=admin@email.com
*/5 * * * *  php /MAGENTO_DIR/shell/config_lock.php validate --file /MAGENTO_DIR/app/etc/config.lock
```
