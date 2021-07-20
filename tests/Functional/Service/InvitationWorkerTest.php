<?php

namespace App\Tests\Functional\Service;

use App\Entity\Invitation;
use App\Entity\User;
use App\Service\InvitationWorker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InvitationWorkerTest extends KernelTestCase
{
    private $em;
    private $invitation_worker;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->invitation_worker = static::getContainer()->get(InvitationWorker::class);
    }

    public function testDeleteWithInvitationNotInUserInvitations(): void
    {
        $invitation = $this->em->getRepository(Invitation::class)->find(2);

        $result = $this->invitation_worker->delete($invitation);
        $this->assertFalse($result);
    }

    public function testDelete(): void
    {
        $invitation = $this->em->getRepository(Invitation::class)->find(3);

        $result = $this->invitation_worker->delete($invitation);
        $this->assertTrue($result);
    }

    public function testCreateWithUserCanNotCreateInvitaions(): void
    {
        $user = $this->em->getRepository(User::class)->find(1);

        $result = $this->invitation_worker->create($user);

        $this->assertNull($result);
    }

    public function testCreate(): void
    {
        $user = $this->em->find(User::class, 2);

        $invitation_code = $this->invitation_worker->create($user);
        $this->assertIsString($invitation_code);

        $invitation = $this->em->getRepository(Invitation::class)->findOneBy([
            'invitation_code' => hash('sha256', $invitation_code)
        ]);
        $this->assertIsObject($invitation);

        $this->assertTrue(in_array($invitation->getId(), $user->getInvitations()[1]));
    }

    public function testUse(): void
    {
        $invitation = $this->em->find(Invitation::class, 3);
        $user = new User();
        $user->setEmail('0');
        $user->setPassword('0');
        $user->setName('0');
        $user->setSurname('0');
        $user->setLocale('0');

        $user = $this->invitation_worker->use($invitation, $user);
        $this->assertEquals($invitation->getCreatedBy(), $user->getInvitedBy());

        $invited_by = $this->em->find(User::class, $invitation->getCreatedBy());

        $invited_by_user_rating = floor($invited_by->getRating() / 2);
        $this->assertEquals($invited_by_user_rating, $user->getRating());
        $this->assertEquals([$invited_by_user_rating, [], []], $user->getInvitations());

        $this->assertFalse(in_array($user->getId(), $invited_by->getInvitations()[1]));
        $this->assertTrue(in_array($user->getId(), $invited_by->getInvitations()[2]));

        $this->assertNull($this->em->find(Invitation::class, 3));
    }

    public function testUseWithCreatedByUserNotExist(): void
    {
        $invitation = $this->em->find(Invitation::class, 1);
        $user = new User();
        $user->setEmail('0');
        $user->setPassword('0');
        $user->setName('0');
        $user->setSurname('0');
        $user->setLocale('0');

        $user = $this->invitation_worker->use($invitation, $user);
        $this->assertEquals($invitation->getCreatedBy(), $user->getInvitedBy());

        $this->assertEquals(0, $user->getRating());
        $this->assertEquals([0, [], []], $user->getInvitations());

        $this->assertNull($this->em->find(Invitation::class, 1));
    }
}
