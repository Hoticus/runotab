<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PasswordRecoveryFirstFormType;
use App\Form\PasswordRecoverySecondFormType;
use App\Service\EmailSender;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthController extends AbstractController
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    #[Route('/login', name: 'app_login')]
    public function login(
        AuthenticationUtils $authentication_utils
    ): Response {
        $error = $authentication_utils->getLastAuthenticationError();
        $last_username = $authentication_utils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $last_username, 'error' => $error]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout()
    {
    }

    #[Route('/restore/password', name: 'app_restore_password_first')]
    public function restorePasswordFirst(Request $request, EmailSender $email_sender)
    {
        $form = $this->createForm(PasswordRecoveryFirstFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository(User::class)->findOneBy(['email' => $form->get('email')->getData()]);

            if (!$user) {
                $this->addFlash('password_recovery_first_error', 'There is no account with this email.');

                return $this->redirectToRoute('app_restore_password_first');
            }

            $recovery_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $recovery_code .= random_int(0, 9);
            }
            $request->getSession()->set('recovery_code', $recovery_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject($this->translator->trans('Password Recovery', domain: 'password_recovery_mail'))
                ->htmlTemplate('security/password_recovery_mail.html.twig')
                ->context([
                    'recovery_code' => $recovery_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $email_sender->send($email);

            $request->getSession()->set('password_recovery_email', $user->getEmail());

            return $this->redirectToRoute('app_restore_password_second');
        }

        return $this->render('security/password_recovery_first.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/restore/password/confirm', name: 'app_restore_password_second')]
    public function restorePasswordSecond(
        Request $request,
        UserPasswordHasherInterface $password_encoder,
        EmailSender $email_sender
    ) {
        if (!$email = $request->getSession()->get('password_recovery_email')) {
            return $this->redirectToRoute('app_restore_password_first');
        }

        $form = $this->createForm(PasswordRecoverySecondFormType::class);
        $form->handleRequest($request);
        $first_form_error = isset($form->getErrors(true)[0]) ? $form->getErrors(true)[0]->getMessage() : null;

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($form->get('recovery_code')->getData() == $request->getSession()->get('recovery_code')) {
                $user->setPassword(
                    $password_encoder->hashPassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );
                $em->flush();

                $request->getSession()->remove('recovery_code');
                $request->getSession()->remove('password_recovery_email');
                $email_sender->removeEmailSendingCooldownEnd();

                $this->addFlash('login_notice', 'Your password has been successfully changed.');

                return $this->redirectToRoute('app_login');
            }

            if ($email_sender->getEmailSendingCooldownEnd() < time()) {
                $recovery_code = "";
                for ($i = 1; $i <= 6; $i++) {
                    $recovery_code .= random_int(0, 9);
                }
                $request->getSession()->set('recovery_code', $recovery_code);

                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@runotab.com', 'Runotab'))
                    ->to($user->getEmail())
                    ->subject($this->translator->trans('Password Recovery', domain: 'password_recovery_mail'))
                    ->htmlTemplate('security/password_recovery_mail.html.twig')
                    ->context([
                        'recovery_code' => $recovery_code,
                        'full_name' => $user->getName() . " " . $user->getSurname()
                    ]);
                $email_sender->send($email);

                $this->addFlash(
                    'password_recovery_second_error',
                    'Verification code is invalid. We have sent you a new mail with a new code.'
                );
            } else {
                $this->addFlash(
                    'password_recovery_second_error',
                    'Verification code is invalid.'
                );
            }

            return $this->redirectToRoute(
                'app_restore_password_second'
            );
        }

        return $this->render('security/password_recovery_second.html.twig', [
            'form' => $form->createView(),
            'first_form_error' => $first_form_error
        ]);
    }

    #[Route('/restore/password/resend', name: 'app_resend_password_recovery_mail')]
    public function resendPasswordRecoveryMail(Request $request, EmailSender $email_sender)
    {
        $password_recovery_email = $request->getSession()->get('password_recovery_email');

        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $password_recovery_email]);

        $recovery_code = $request->getSession()->get('recovery_code');

        if (!$user || !$recovery_code) {
            return $this->redirectToRoute('app_restore_password_first');
        }

        if ($email_sender->getEmailSendingCooldownEnd() < time()) {
            $recovery_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $recovery_code .= random_int(0, 9);
            }
            $request->getSession()->set('recovery_code', $recovery_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject($this->translator->trans('Password Recovery', domain: 'password_recovery_mail'))
                ->htmlTemplate('security/password_recovery_mail.html.twig')
                ->context([
                    'recovery_code' => $recovery_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $email_sender->send($email);

            $this->addFlash(
                'password_recovery_second_notice',
                'We have sent you a new mail with a new code.'
            );
        } else {
            $this->addFlash(
                'password_recovery_second_error',
                'Wait 60 seconds before sending a new mail.'
            );
        }

        return $this->redirectToRoute(
            'app_restore_password_second'
        );
    }
}
