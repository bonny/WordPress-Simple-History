<?php
namespace Helper;

class Acceptance extends \Codeception\Module
{
    /**
     * Ensure required test themes are installed before the suite runs.
     *
     * Twenty Sixteen is needed by SimpleMenuLoggerCest (classic nav menus)
     * and SimpleThemeLoggerCest (theme install/delete cycle).
     * The theme test deletes it at the end and re-installs it, but if the
     * suite was interrupted, it may be missing on the next run.
     */
    public function _beforeSuite($settings = [])
    {
        $themeDir = '/wordpress/wp-content/themes/twentysixteen';
        $zipFile = '/srv/tests/_data/twentysixteen.2.6.zip';

        if (!is_dir($themeDir) && file_exists($zipFile)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) === true) {
                $zip->extractTo('/wordpress/wp-content/themes/');
                $zip->close();
                codecept_debug('Installed Twenty Sixteen theme from zip');
            }
        }
    }
}
