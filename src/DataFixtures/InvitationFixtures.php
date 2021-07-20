<?php

namespace App\DataFixtures;

use App\Entity\Invitation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class InvitationFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $invitations = [str_repeat('0', 16), str_repeat('1', 16), str_repeat('2', 16)];

        foreach ($invitations as $invitation_code_key => $invitation_code) {
            $invitation = new Invitation();

            $invitation->setInvitationCode($invitation_code);
            $invitation->setCreatedBy($invitation_code_key);

            $manager->persist($invitation);
        }

        $manager->flush();
    }
}
