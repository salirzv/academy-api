<?php

namespace App\Controller\Landing\Home;

use App\Entity\Course;
use App\Entity\DownloadToken;
use App\Entity\Session;
use App\Utils\CustomFunctions;
use App\Utils\ImageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use DateTime;

class LandingHomeController extends AbstractController
{
    /**
     * @Route("/home/getrecommendedcourses", name="landingGetRecommendedCourses", methods={"POST"})
     */
    public function landingGetRecommendedCourses(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $courses = $entityManager->getRepository(Course::class)->findAll();
        return new JsonResponse($serializer->serialize($courses, 'json', [
            'ignored_attributes' => ['categories']
        ]));
    }

}