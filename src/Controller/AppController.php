<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Intl\Timezones;
use Symfony\Component\Routing\Annotation\Route;

class AppController extends AbstractController
{
    #[Route('/app/set/timezone', name: 'app_set_timezone')]
    public function setTimezone(Request $request): Response
    {
        $timezone = $request->query->get('timezone');
        $return = $request->query->get('return');

        if ($return && Timezones::exists($timezone)) {
            $timezone_cookie = Cookie::create('timezone')->withValue($timezone)->withSecure();
            $response = new Response();
            $response->headers->setCookie($timezone_cookie);
            $response->send();

            return $this->redirect($return);
        }

        throw new BadRequestHttpException();
    }
}
