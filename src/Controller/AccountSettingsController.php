<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\Photo;
use App\Form\SelectAvatarPhotoFormType;
use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AccountSettingsController extends AbstractController
{
    /**
     * @Route("/account/settings", name="account_settings")
     */
    public function index(Request $request, FileUploader $file_uploader): Response
    {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();

        $user_invitations = [];
        foreach ($user->getInvitations()[1] as $invitation_id) {
            $user_invitations[] = $em->getRepository(Invitation::class)->find($invitation_id);
        }
        $avatar_photo = $user->getAvatarPhoto()
            ? 'uploads/photos/' . $em->getRepository(Photo::class)->find($user->getAvatarPhoto())->getFileName()
            : 'build/images/no-photo.png';

        $photo = new Photo();
        $form = $this->createForm(SelectAvatarPhotoFormType::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $photo_file = $form->get('photo')->getData();
            $photo_filename = $file_uploader->upload($photo_file, $this->getParameter('photos_directory'));
            $photo->setFileName($photo_filename);

            $em->persist($photo);
            $user->setAvatarPhoto($photo->getId());
            $em->flush();

            return $this->redirectToRoute('account_settings');
        }

        return $this->render('account_settings/index.html.twig', [
            'form' => $form->createView(),
            'avatar_photo' => $avatar_photo,
            'user_invitations' => $user_invitations
        ]);
    }

    /**
     * @Route("account/settings/create/invitation", name="create_invitation")
     */
    public function createInvitation(): Response
    {
        $user = $this->getUser();
        $user_invitations = $user->getInvitations();
        if ($user_invitations[0] > 0) {
            $em = $this->getDoctrine()->getManager();

            $invitation = new Invitation();

            $invitation_code = "";
            while (
                !$invitation_code
                || $em->getRepository(Invitation::class)->findOneBy(['invitation_code' => $invitation_code])
            ) {
                $invitation_code = "";
                $invitation_code_characters = array_merge(range(0, 9), range("A", "Z"));
                for ($i = 1; $i <= 16; $i++) {
                    $invitation_code .= $invitation_code_characters[
                        random_int(0, count($invitation_code_characters) - 1)
                    ];
                }
            }
            $invitation->setInvitationCode(hash("sha256", $invitation_code));
            $this->addFlash(
                'create_invitation_success_invitation_code',
                substr(chunk_split($invitation_code, 4, '-'), 0, -1)
            );

            $invitation->setCreatedBy($user->getId());

            $em->persist($invitation);

            $user_invitations[0]--;
            $user_invitations[1][] = $invitation->getId();
            $user->setInvitations($user_invitations);

            $em->flush();
        }

        return $this->redirectToRoute('account_settings');
    }

    /**
     * @Route("account/settings/delete/invitation/{invitation_id<\d+>}", name="delete_invitation")
     */
    public function deleteInvitation(int $invitation_id): Response
    {
        $em = $this->getDoctrine()->getManager();
        $invitation = $em->getRepository(Invitation::class)->find($invitation_id);

        if ($invitation) {
            $user = $this->getUser();
            $user_invitations = $user->getInvitations();

            if (in_array($invitation_id, $user_invitations[1])) {
                $user_invitations[0]++;
                unset($user_invitations[1][array_search($invitation_id, $user_invitations[1])]);
                $user_invitations[1] = array_values($user_invitations[1]);
                $user->setInvitations($user_invitations);

                $em->remove($invitation);
                $em->flush();
            }
        }

        return $this->redirectToRoute('account_settings');
    }
}
