<?php

namespace App\Controller\Security;

use App\Entity\AccessToken;
use App\Entity\ResetToken;
use App\Entity\User;
use App\Utils\CustomFunctions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/register", name="appRegister", methods={"POST"})
     */
    public function appRegister(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $credentials = $request->request->all();
        $response['hasError'] = true;
        foreach ($credentials as $credential) {
            if (empty($credential)) {
                $response['errorMessage'] = 'اطلاعت ناقص وارد شده است';
                return new JsonResponse(json_encode($response));
            }
        }
        if (!filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            $response['errorMessage'] = 'ایمیل معتبر نمی باشد';
            return new JsonResponse(json_encode($response));
        }
        if (mb_strlen($credentials['password']) < 8) {
            $response['errorMessage'] = 'رمز عبور حداقل باید 8 کاراکتر باشد';
            return new JsonResponse(json_encode($response));
        }
        if ($credentials['password'] !== $credentials['password-c']) {
            $response['errorMessage'] = 'کلمات عبور تفاوت دارند';
            return new JsonResponse(json_encode($response));
        }

        $prev_user = $entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);
        if (!empty($prev_user)) {
            $response['errorMessage'] = 'ایمیل در سیستم موجود می باشد';
            return new JsonResponse(json_encode($response));
        }

        $user = (new User())->setEmail($credentials['email']);
        $user->setPassword($passwordHasher->hashPassword($user, $credentials['password']));

        $entityManager->persist($user);
        $entityManager->flush();

        $response['hasError'] = false;

        return new JsonResponse(json_encode($response));
    }

    /**
     * @Route("/login", name="appLogin", methods={"POST"})
     */
    public function appLogin(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $credentials = $request->request->all();
        $response['hasError'] = true;
        foreach ($credentials as $credential) {
            if (empty($credential)) {
                $response['errorMessage'] = 'ایمیل یا رمز عبور معتبر نمی باشد';
                return new JsonResponse(json_encode($response));
            }
        }
        if (!filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            $response['errorMessage'] = 'ایمیل یا رمز عبور معتبر نمی باشد';
            return new JsonResponse(json_encode($response));
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);
        if (empty($user)) {
            $response['errorMessage'] = 'ایمیل یا رمز عبور معتبر نمی باشد';
            return new JsonResponse(json_encode($response));
        }

        if ($passwordHasher->isPasswordValid($user, $credentials['password'])) {
            $token = CustomFunctions::generateString(32) . uniqid('token', true);
            $accessToken = (new AccessToken())->setOwner($user)->setToken($token)->setIsRememberMe($credentials['remember-me'] === 'true')->setLastUsed(new \DateTime());
            $entityManager->persist($accessToken);
            $entityManager->flush();
            $response['hasError'] = false;

            if ($accessToken->getIsRememberMe()){
                $cookie = (new Cookie('token'))->withValue($accessToken->getToken())->withHttpOnly()->withSecure(false)->withDomain('.academy.test')->withExpires(strtotime('Fri, 20-May-2099 15:25:52 GMT'));
            }else{
                $cookie = (new Cookie('token'))->withValue($accessToken->getToken())->withHttpOnly()->withSecure(false)->withDomain('.academy.test');
            }
            $r_response = new JsonResponse(json_encode($response));
            $r_response->headers->setCookie($cookie);
            return $r_response;
        } else {
            $response['errorMessage'] = 'ایمیل یا رمز عبور معتبر نمی باشد';
            return new JsonResponse(json_encode($response));
        }
    }

    /**
     * @Route("/logout", name="appLogout", methods={"POST"})
     */
    public function appLogout(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        if ($this->getUser() === null){
            throw new BadRequestException();
        }

        $currentAccessToken = $entityManager->getRepository(AccessToken::class)->findOneBy(['token' => $request->cookies->get('token')]);
        $entityManager->remove($currentAccessToken);
        $entityManager->flush();

        $cookie = (new Cookie('token'))->withExpires(strtotime('Fri, 20-May-2011 15:25:52 GMT'))->withSecure(false)->withDomain('.academy.test');
        $response = new JsonResponse();
        $response->headers->setCookie($cookie);
        return $response;
    }

    /**
     * @Route("/forgot", name="appForgot", methods={"POST"})
     */
    public function appForgot(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $response['hasError'] = true;
        $email = $request->request->get('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $response['errorMessage'] = 'ایمیل معتبر نمی باشد';
            return new JsonResponse(json_encode($response));
        }
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (empty($user)){
            $response['errorMessage'] = 'حساب کاربری شما یافت نشد';
            return new JsonResponse(json_encode($response));
        }
        $token = CustomFunctions::generateString(32).uniqid('pw_reset', true);
        $resetToken = (new ResetToken())->setToken($token)->setEmail($user->getEmail())->setCreatedAt(new \DateTime());
        $entityManager->persist($resetToken);
        $entityManager->flush();

        $mail = (new TemplatedEmail())
            ->from('support@academy.test')
            ->to($email)
            ->subject('آکادمی | بازیابی رمز عبور')
            ->htmlTemplate('email/forgot.html.twig')
            ->context([
                'link' => "http://academy.test/resetpw?token=$token"
            ]);

        try {
            $mailer->send($mail);
        } catch (TransportExceptionInterface $e) {
        }
        $response['hasError'] = false;
        return new JsonResponse(json_encode($response));
    }

    /**
     * @Route("/resetpw", name="appReset", methods={"POST"})
     */
    public function appReset(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $response['hasError'] = true;

        $credentials = [
            'password' => $request->request->get('password'),
            'password-c' => $request->request->get('password-c'),
            'token' => $request->request->get('token'),
        ];

        foreach ($credentials as $credential) {
            if (empty($credential)){
                throw new BadRequestException();
            }
        }

        $resetToken = $entityManager->getRepository(ResetToken::class)->findOneBy(['token' => $credentials['token']]);
        if (empty($resetToken)){
            $response['errorMessage'] = 'لینک بازیابی معتبر نمی باشد';
            return new JsonResponse(json_encode($response));
        }
        if (((new \DateTime())->getTimestamp() - $resetToken->getCreatedAt()->getTimestamp())  > 10800){
            $response['errorMessage'] = 'لینک بازیابی منقضی شده است';
            return new JsonResponse(json_encode($response));
        }

        if (mb_strlen($credentials['password']) < 8) {
            $response['errorMessage'] = 'رمز عبور حداقل باید 8 کاراکتر باشد';
            return new JsonResponse(json_encode($response));
        }
        if ($credentials['password'] !== $credentials['password-c']) {
            $response['errorMessage'] = 'کلمات عبور تفاوت دارند';
            return new JsonResponse(json_encode($response));
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $resetToken->getEmail()]);
        if (empty($user)){
            throw new BadRequestException();
        }

        $user->setPassword($passwordHasher->hashPassword($user, $credentials['password']));

        $entityManager->remove($resetToken);

        $entityManager->persist($user);
        $entityManager->flush();

        $response['hasError'] = false;

        return new JsonResponse(json_encode($response));
    }

    /**
     * @Route("/userdata", name="appUserData", methods={"POST"})
     */
    public function appUserData(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() === null) {
            return new JsonResponse(false);
        } else {
            $user['email'] = $this->getUser()->getEmail();
            foreach ($this->getUser()->getRoles() as $role) {
                $user['roles'][] = $role;
            }
            return new JsonResponse(json_encode($user));
        }
    }
}