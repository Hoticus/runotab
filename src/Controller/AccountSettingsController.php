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
    public function index(Request $request, PhotoWorker $photo_worker, InvitationWorker $invitation_worker): Response
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
        $select_avatar_photo_form = $this->createForm(SelectAvatarPhotoFormType::class, $photo);
        $select_avatar_photo_form->handleRequest($request);

        if ($select_avatar_photo_form->isSubmitted() && $select_avatar_photo_form->isValid()) {
            $photo = $photo_worker->upload($photo, $select_avatar_photo_form->get('photo')->getData());

            $user->setAvatarPhoto($photo->getId());
            $em->flush();

            return $this->redirectToRoute('account_settings');
        }

        $delete_avatar_photo_form = $this->get('form.factory')->createNamedBuilder('delete_avatar_photo_form')
            ->getForm();
        $delete_avatar_photo_form->handleRequest($request);

        if ($delete_avatar_photo_form->isSubmitted() && $delete_avatar_photo_form->isValid()) {
            $photo_worker->delete($em->find(Photo::class, $user->getAvatarPhoto()));

            $user->setAvatarPhoto(null);
            $em->flush();

            return $this->redirectToRoute('account_settings');
        }

        $create_invitation_form = $this->get('form.factory')->createNamedBuilder('create_invitation_form')->getForm();
        $create_invitation_form->handleRequest($request);

        if ($create_invitation_form->isSubmitted() && $create_invitation_form->isValid()) {
            if ($invitation_code = $invitation_worker->create($this->getUser())) {
                $this->addFlash(
                    'create_invitation_success_invitation_code',
                    substr(chunk_split($invitation_code, 4, '-'), 0, -1)
                );
            }

            return $this->redirectToRoute('account_settings');
        }

        return $this->render('account_settings/index.html.twig', [
            'select_avatar_photo_form' => $select_avatar_photo_form->createView(),
            'delete_avatar_photo_form' => $delete_avatar_photo_form->createView(),
            'create_invitation_form' => $create_invitation_form->createView(),
            'avatar_photo' => $avatar_photo,
            'user_invitations' => $user_invitations,
            'invited_users_photos' => $invited_users_photos
        ]);
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
