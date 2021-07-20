<?php

namespace App\Service;

use App\Entity\Invitation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class InvitationWorker
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Hash an invitation code
     *
     * @param string $invitation_code
     * @return string Hashed invitation code
     */
    public function hash(string $invitation_code): string
    {
        return hash('sha256', $invitation_code);
    }

    /**
     * Create an invitation and change user invitations
     *
     * @param UserInterface $user
     * @return string|null Invitation code or null if user can't create a new invitation
     */
    public function create(UserInterface $user): ?string
    {
        $em = $this->em;

        $user_invitations = $user->getInvitations();
        if ($user_invitations[0] > 0) {
            $invitation = new Invitation();

            $invitation_code = "";
            while (
                !$invitation_code
                || $em->getRepository(Invitation::class)
                    ->findOneBy(['invitation_code' => $this->hash($invitation_code)])
            ) {
                $invitation_code = "";
                $invitation_code_characters = array_merge(range(0, 9), range("A", "Z"));
                for ($i = 1; $i <= 16; $i++) {
                    $invitation_code .= $invitation_code_characters[random_int(
                        0,
                        count($invitation_code_characters) - 1
                    )];
                }
            }

            $invitation->setInvitationCode($this->hash($invitation_code));
            $invitation->setCreatedBy($user->getId());

            $em->persist($invitation);

            $user_invitations[0]--;
            $user_invitations[1][] = $invitation->getId();
            $user->setInvitations($user_invitations);

            $em->flush();

            return $invitation_code;
        }

        return null;
    }

    /**
     * Delete the invitation and change user invitations
     *
     * @param Invitation $invitation
     * @param UserInterface|null $user
     * If not null, will check if user have created this invitation before deletion and if no, return false.
     * If null, will change user invitations in the user that created the invitation.
     * @return bool True if deleted, false if not
     */
    public function delete(Invitation $invitation, ?UserInterface $user = null): bool
    {
        $em = $this->em;

        $user = $user ?? $em->find(User::class, $invitation->getCreatedBy());
        $user_invitations = $user->getInvitations();

        if (in_array($invitation->getId(), $user_invitations[1])) {
            $user_invitations[0]++;
            unset($user_invitations[1][array_search($invitation->getId(), $user_invitations[1])]);
            $user_invitations[1] = array_values($user_invitations[1]);
            $user->setInvitations($user_invitations);

            $em->remove($invitation);
            $em->flush();

            return true;
        }

        return false;
    }

    /**
     * Use the invitation (delete it), set invited_by, rating, invitations in the user and save it into DB
     *
     * @param Invitation $invitation
     * @param UserInterface $user
     * @return UserInteface
     */
    public function use(Invitation $invitation, UserInterface $user): UserInterface
    {
        $em = $this->em;

        $user->setInvitedBy($invitation->getCreatedBy());
        if ($invited_by = $em->find(User::class, $invitation->getCreatedBy())) {
            $invited_by_user_invitations = $invited_by->getInvitations();
            unset($invited_by_user_invitations[1][array_search(
                $invitation->getId(),
                $invited_by_user_invitations[1]
            )]);
            $invited_by_user_invitations[1] = array_values($invited_by_user_invitations[1]);
            $user->setRating(floor($invited_by->getRating() / 2));
            $user->setInvitations([floor($invited_by->getRating() / 2), [], []]);
        }

        $em->persist($user);
        $em->remove($invitation);
        if ($invited_by) {
            $invited_by_user_invitations[2][] = $user->getId();
            $invited_by->setInvitations($invited_by_user_invitations);
        }
        $em->flush();

        return $user;
    }
}
