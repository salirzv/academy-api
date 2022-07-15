<?php

namespace App\Controller\Panel;

use App\Entity\Course;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Routing\Annotation\Route;

class PanelIndexController extends AbstractController
{
    /**
     * @Route("/dashboard/getusercourses", name="dashboardGetUserCourses", methods={"POST"})
     */
    public function dashboardGetUserCourses(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $paid_orders = $entityManager->getRepository(Order::class)->findBy([
            'owner' => $this->getUser(),
            'status' => 'paid'
        ]);
        $courses = [];
        foreach ($paid_orders as $paid_order){
            $order_items = $paid_order->getItems();
            foreach ($order_items as $order_item){
                $courses[] = $order_item;
            }
        }
        return new JsonResponse($serializer->serialize($courses, 'json', [
            'ignored_attributes' => ['categories']
        ]));
    }

}