<?php

namespace App\Controller\Admin\Category;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class AdminCategoryController extends AbstractController
{
    /**
     * @Route("/admin/categories", name="adminCategories", methods={"POST"})
     */
    public function adminCategories(EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        return new JsonResponse($serializer->serialize($categories, 'json', [
            'ignored_attributes' => ['courses']
        ]));
    }

    /**
     * @Route("/admin/categories/new", name="adminCategoriesNew", methods={"POST"})
     */
    public function adminCategoriesNew(EntityManagerInterface $entityManager, Request $request): Response
    {
        $response['hasError'] = true;

        $credentials = [
            'en-name' => $request->request->get('en-name'),
            'fa-name' => $request->request->get('fa-name'),
        ];

        foreach ($credentials as $credential){
            if (empty($credential)){
                $response['errorMessage'] = 'اطلاعات ناقص وارد شده است';
                return new JsonResponse(json_encode($response));
            }
        }

        $category = (new Category())->setEnName($credentials['en-name'])->setFaName($credentials['fa-name']);
        $entityManager->persist($category);
        $entityManager->flush();

        $response['hasError'] = false;

        return new JsonResponse(json_encode($response));
    }
}