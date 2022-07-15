<?php

namespace App\Controller\Landing\Search;

use App\Entity\Category;
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

class LandingSearchController extends AbstractController
{
    /**
     * @Route("/getsearchresult", name="landingGetSearchResult", methods={"POST"})
     */
    public function landingGetSearchResult(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $response['hasError'] = true;
        $response['results'] = [];

        $credentials = [
            'query' => $request->request->get('query'),
            'category' => $request->request->get('category')
        ];
        if (empty($credentials['query']) && empty($credentials['category'])){
            $response['hasError'] = false;
            $response['results'] = $entityManager->getRepository(Course::class)->findAll();
            return new JsonResponse($serializer->serialize($response, 'json', [
                'ignored_attributes' => ['categories']
            ]));
        }
        if (!empty($credentials['query']) && empty($credentials['category'])){
            $response['hasError'] = false;
            $response['results'] = $entityManager->createQueryBuilder()
                ->select('c')
                ->from('App:Course', 'c')
                ->innerJoin('c.categories' , 'ca')
                ->where('c.title LIKE :query')
                ->orWhere('ca.en_name LIKE :query')
                ->orWhere('ca.fa_name LIKE :query')
                ->setParameters([
                    'query' => '%'.$credentials['query'].'%'
                ])->getQuery()->execute();
            return new JsonResponse($serializer->serialize($response, 'json', [
                'ignored_attributes' => ['categories']
            ]));
        }
        if (empty($credentials['query']) && !empty($credentials['category'])){
            $category = $entityManager->getRepository(Category::class)->findOneBy(['en_name' => $credentials['category']]);
            if (empty($category)){
                return new JsonResponse(json_encode($response));
            }
            $response['hasError'] = false;
            $response['results'] = $category->getCourses();
            $response['category'] = $category;
            return new JsonResponse($serializer->serialize($response, 'json', [
                'ignored_attributes' => ['categories']
            ]));
        }
        if (!empty($credentials['query']) && !empty($credentials['category'])){
            $category = $entityManager->getRepository(Category::class)->findOneBy(['en_name' => $credentials['category']]);
            if (empty($category)){
                return new JsonResponse(json_encode($response));
            }
            $response['hasError'] = false;
            $response['results'] = $entityManager->createQueryBuilder()
                ->select('c')
                ->from('App:Course', 'c')
                ->innerJoin('c.categories', 'ca')
                ->where('c.title LIKE :query')
                ->andWhere('ca = :category')
                ->setParameters([
                    'query' => '%'.$credentials['query'].'%',
                    'category' => $category
                ])->getQuery()->execute();
            dump($response['results']);
            return new JsonResponse($serializer->serialize($response, 'json', [
                'ignored_attributes' => ['categories']
            ]));
        }
    }

    /**
     * @Route("/getcategories", name="landingGetCategories", methods={"POST"})
     */
    public function landingGetCategories(EntityManagerInterface $entityManager, SerializerInterface $serializer): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        return new JsonResponse($serializer->serialize($categories, 'json', [
            'ignored_attributes' => ['courses']
        ]));
    }
}