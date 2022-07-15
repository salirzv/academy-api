<?php

namespace App\Controller\Panel\Setting;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Morilog\Jalali\Jalalian;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Annotation\Route;

class PanelSettingController extends AbstractController
{
    /**
     * @Route("/dashboard/settings/changepw", name="dashboardSettingsChangePw", methods={"POST"})
     */
    public function dashboardSettingsChangePw(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $response['hasError'] = true;

        $credentials = [
            'cp' => $request->request->get('cp'),
            'np' => $request->request->get('np'),
            'cnp' => $request->request->get('cnp')
        ];

        foreach ($credentials as $credential){
            if (empty($credential)){
                throw new BadRequestException();
            }
        }
        if (!$passwordHasher->isPasswordValid($this->getUser(), $credentials['cp'])){
            $response['errorMessage'] = 'کلمه عبور فعلی معتبر نمی باشد';
            return new JsonResponse(json_encode($response));
        }

        if (mb_strlen($credentials['np']) < 8) {
            $response['errorMessage'] = 'رمز عبور جدید حداقل باید 8 کاراکتر باشد';
            return new JsonResponse(json_encode($response));
        }
        if ($credentials['np'] !== $credentials['cnp']) {
            $response['errorMessage'] = 'کلمات عبور جدید وارد شده تفاوت دارند';
            return new JsonResponse(json_encode($response));
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $this->getUser()->getUserIdentifier()]);
        $user->setPassword($passwordHasher->hashPassword($user, $credentials['np']));
        $entityManager->persist($user);
        $entityManager->flush();

        $response['hasError'] = false;
        return new JsonResponse(json_encode($response));
    }

}