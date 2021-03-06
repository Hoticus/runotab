<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\EmailVerificationFormType;
use App\Form\RegistrationFormType;
use App\Service\EmailSender;
use App\Service\InvitationWorker;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EmailSender $email_sender,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $password_hasher
    ): Response {
        $user = new User();
        $request->getSession()->set('user', $user);
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        $first_form_error = isset($form->getErrors(true)[0]) ? $form->getErrors(true)[0]->getMessage() : null;

        if ($form->isSubmitted() && $form->isValid()) {
            if (
                !$invitation = $this->getDoctrine()->getRepository(Invitation::class)->findOneByInvitationCode(
                    str_replace('-', '', $form->get('invitationCode')->getData())
                )
            ) {
                $this->addFlash('registration_error', 'Please enter a valid invitation code.');

                return $this->redirectToRoute('app_register');
            }
            $request->getSession()->set('invitation_code', $invitation->getInvitationCode());

            $user->setPassword(
                $password_hasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            $verification_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $verification_code .= random_int(0, 9);
            }
            $request->getSession()->set('verification_code', $verification_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject($this->translator->trans('Email Confirmation', domain: 'email_verification_mail'))
                ->htmlTemplate('security/email_verification_mail.html.twig')
                ->context([
                    'verification_code' => $verification_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $this->email_sender->send($email);

            return $this->redirectToRoute(
                'app_verify_email'
            );
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
            'first_form_error' => $first_form_error
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TokenStorageInterface $token_storage,
        RememberMeHandlerInterface $remember_me,
        InvitationWorker $invitation_worker
    ) {
        $user = $request->getSession()->get('user');
        $verification_code = $request->getSession()->get('verification_code');

        if (!$user || !$verification_code) {
            return $this->redirectToRoute('app_register');
        }

        $form = $this->createForm(EmailVerificationFormType::class);
        $form->handleRequest($request);
        $first_form_error = isset($form->getErrors(true)[0]) ? $form->getErrors(true)[0]->getMessage() : null;

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('verification_code')->getData() == $verification_code) {
                $em = $this->getDoctrine()->getManager();
                $invitation = $em->getRepository(Invitation::class)
                    ->findOneBy(['invitation_code' => $request->getSession()->get('invitation_code')]);

                $request->getSession()->remove('user');
                $request->getSession()->remove('verification_code');
                $this->email_sender->removeEmailSendingCooldownEnd();
                $request->getSession()->remove('invitation_code');

                if (!$invitation) {
                    $this->addFlash('registration_error', 'The invitation code is outdated.');

                    return $this->redirectToRoute('app_register');
                }

                $user->setLocale($request->getLocale());
                $request->getSession()->set('_locale', $request->getLocale());

                $user = $invitation_worker->use($invitation, $user);

                $token = new PostAuthenticationToken($user, 'main', $user->getRoles());
                $token_storage->setToken($token);
                $request->getSession()->set('_security_main', serialize($token));
                $remember_me->createRememberMeCookie($user);

                return $this->redirectToRoute('default');
            }

            if ($this->email_sender->getEmailSendingCooldownEnd() < time()) {
                $verification_code = "";
                for ($i = 1; $i <= 6; $i++) {
                    $verification_code .= random_int(0, 9);
                }
                $request->getSession()->set('verification_code', $verification_code);

                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@runotab.com', 'Runotab'))
                    ->to($user->getEmail())
                    ->subject($this->translator->trans('Email Confirmation', domain: 'email_verification_mail'))
                    ->htmlTemplate('security/email_verification_mail.html.twig')
                    ->context([
                        'verification_code' => $verification_code,
                        'full_name' => $user->getName() . " " . $user->getSurname()
                    ]);
                $this->email_sender->send($email);

                $this->addFlash(
                    'email_verification_error',
                    'This code is invalid. We have sent you a new mail with a new code.'
                );
            } else {
                $this->addFlash(
                    'email_verification_error',
                    'This code is invalid.'
                );
            }

            return $this->redirectToRoute(
                'app_verify_email'
            );
        }

        return $this->render('security/email_verification.html.twig', [
            'emailVerificationForm' => $form->createView(),
            'first_form_error' => $first_form_error
        ]);
    }

    #[Route('/verify/email/resend', name: 'app_resend_verification_mail')]
    public function resendVerififcationMail(Request $request)
    {
        $user = $request->getSession()->get('user');
        $verification_code = $request->getSession()->get('verification_code');

        if (!$user || !$verification_code) {
            return $this->redirectToRoute('app_register');
        }

        if ($this->email_sender->getEmailSendingCooldownEnd() < time()) {
            $verification_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $verification_code .= random_int(0, 9);
            }
            $request->getSession()->set('verification_code', $verification_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject($this->translator->trans('Email Confirmation', domain: 'email_verification_mail'))
                ->htmlTemplate('security/email_verification_mail.html.twig')
                ->context([
                    'verification_code' => $verification_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $this->email_sender->send($email);

            $this->addFlash(
                'email_verification_notice',
                'We have sent you a new mail with a new code.'
            );
        } else {
            $this->addFlash(
                'email_verification_error',
                'Wait 60 seconds before sending a new mail.'
            );
        }

        return $this->redirectToRoute(
            'app_verify_email'
        );
    }
}
