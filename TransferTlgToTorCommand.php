<?php

namespace Bundles\CoreBundle\Command;

use Bundles\CoreBundle\Model\Students\UserQuery;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TransferTlgToTorCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('core:transfer_tlg_to_tor');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->getContainer()->get('database_switcher')->changeDatabase('tlg');

        $users = UserQuery::create()->find();

        foreach ($users as $user)
        {
            $global_id = $user->getGlobalUserId();
            $value = $user->toArray();

            $this->getContainer()->get('database_switcher')->changeDatabase('tor');

            if (!UserQuery::create()->findOneByGlobalUserId($global_id))
            {
                $this->getContainer()->get('sqs')->sendMessage('transfer-student', serialize([
                    'oldDB' => 'tlg',
                    'newDB' => 'tor',
                    'data' => serialize($value),
                ]));
            }
        }
    }
}
