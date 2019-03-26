<?php

namespace Xfrocks\Image;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use Xfrocks\Image\DevHelper\SetupTrait;

class Setup extends AbstractSetup
{
    use SetupTrait;
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->doAlterTables($this->getAlters());
    }

    protected function getAlters1()
    {
        $alters = [];

        $alters['xf_thread'] = [
            'bdimage_image' => function (Alter $table) {
                $table->addColumn('bdimage_image', 'TEXT')->nullable();
            }
        ];

        $alters['xf_forum'] = [
            'bdimage_last_post_image' => function (Alter $table) {
                $table->addColumn('bdimage_last_post_image', 'TEXT')->nullable();
            }
        ];

        return $alters;
    }
}
