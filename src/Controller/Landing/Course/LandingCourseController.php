<?php

namespace App\Controller\Landing\Course;

use App\Entity\Course;
use App\Entity\DownloadToken;
use App\Entity\Session;
use App\Utils\CustomFunctions;
use App\Utils\ImageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use DateTime;

class LandingCourseController extends AbstractController
{
    /**
     * @Route("/course/getSingleCourse/{slug}", name="landingGetCourseInfo", methods={"POST"})
     */
    public function landingGetCourseInfo(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, $slug): Response
    {
        $course['info'] = $entityManager->getRepository(Course::class)->findOneBy(['slug' => $slug]);
        $course['sessions'] = $entityManager->getRepository(Session::class)->findBy(['course' => $course]);
        $result = $serializer->serialize($course, 'json', ['ignored_attributes' => ['owner', 'course', 'categories']]);

        return new JsonResponse($result);
    }

    /**
     * @Route("/dt/{id}", name="landingGetDownloadToken", methods={"POST"})
     */
    public function landingGetDownloadToken(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, $id): Response
    {
        $session = $entityManager->getRepository(Session::class)->find($id);
        if (empty($session)){
            throw new BadRequestException();
        }
        $course = $session->getCourse();

        if (!$session->getIsFree()){
            if ($this->getUser() === null){
                throw new BadRequestException();
            }
            $orders = $entityManager->createQueryBuilder()
                ->select('o')->from('App:Order', 'o')
                ->innerJoin('o.items', 'c')
                ->where('c = :course')
                ->andWhere('o.status = :status')
                ->andWhere('o.owner = :owner')->setParameters([
                    'course' => $course,
                    'owner' => $this->getUser(),
                    'status' => 'paid'
                ])->getQuery()->execute();
            if (!empty($orders)){
                $token = CustomFunctions::generateString(32).uniqid('download', true);
                $dt = (new DownloadToken())->setSession($session)->setToken($token)->setCreatedAt(new DateTime());
                $entityManager->persist($dt);
                $entityManager->flush();
                return new JsonResponse(json_encode($token));
            }else{
                throw new BadRequestException();
            }
        }else{
            $token = CustomFunctions::generateString(32).uniqid('download', true);
            $dt = (new DownloadToken())->setSession($session)->setToken($token)->setCreatedAt(new DateTime());
            $entityManager->persist($dt);
            $entityManager->flush();
            return new JsonResponse(json_encode($token));
        }
    }

    /**
     * @Route("/course/isowned/{id}", name="landingCourseIsOwned", methods={"POST"})
     */
    public function landingCourseIsOwned(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer, $id): Response
    {
        $result = false;
        if ($this->getUser() === null){
            return new JsonResponse(json_encode($result));
        }
        $course = $entityManager->getRepository(Course::class)->find($id);
        if (empty($course)){
            throw new BadRequestException();
        }

        $orders = $entityManager->createQueryBuilder()
            ->select('o')->from('App:Order', 'o')
            ->innerJoin('o.items', 'c')
            ->where('c = :course')
            ->andWhere('o.owner = :owner')->setParameters([
                'course' => $course,
                'owner' => $this->getUser()
            ])->getQuery()->execute();
        foreach ($orders as $order) {
            if ($order->getStatus() === 'paid') {
                $result = true;
                return new JsonResponse(json_encode($result));
            }
        }
        return new JsonResponse(json_encode($result));
    }
}