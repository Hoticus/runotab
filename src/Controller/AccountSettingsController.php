<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Photo;
use App\Entity\User;
use App\Form\SelectAvatarPhotoFormType;
use App\Service\InvitationWorker;
use App\Service\PhotoWorker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountSettingsController extends AbstractController
{
    #[Route('/account/settings', name: 'account_settings')]
    public function index(Request $request, PhotoWorker $photo_worker): Response
    {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        $user_invitations = [];
        foreach ($user->getInvitations()[1] as $invitation_id) {
            $user_invitations[] = $em->find(Invitation::class, $invitation_id);
        }
        $users = $em->getRepository(User::class);
        $invited_users_photos = $users->getInvitedUsersPhotos($user);
        $avatar_photo = $users->getAvatarPhotoPath($user);

        $photo = new Photo();
        $form = $this->createForm(SelectAvatarPhotoFormType::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photo = $photo_worker->upload($photo, $form->get('photo')->getData());

            $user->setAvatarPhoto($photo->getId());
            $em->flush();

            return $this->redirectToRoute('account_settings');
        }

        return $this->render('account_settings/index.html.twig', [
            'form' => $form->createView(),
            'avatar_photo' => $avatar_photo,
            'user_invitations' => $user_invitations,
            'invited_users_photos' => $invited_users_photos
        ]);
    }

    #[Route('/account/settings/create/invitation', name: 'create_invitation')]
    public function createInvitation(InvitationWorker $invitation_worker): Response
    {
        if ($invitation_code = $invitation_worker->create($this->getUser())) {
            $this->addFlash(
                'create_invitation_success_invitation_code',
                substr(chunk_split($invitation_code, 4, '-'), 0, -1)
            );
        }

        return $this->redirectToRoute('account_settings');
    }

    #[Route('/account/settings/delete/invitation/{invitation_id<\d+>}', name: 'delete_invitation')]
    public function deleteInvitation(int $invitation_id, InvitationWorker $invitation_worker): Response
    {
        $invitation = $this->getDoctrine()->getManager()->find(Invitation::class, $invitation_id);

        if ($invitation) {
            $invitation_worker->delete($invitation, $this->getUser());
        }

        return $this->redirectToRoute('account_settings');
    }
}
